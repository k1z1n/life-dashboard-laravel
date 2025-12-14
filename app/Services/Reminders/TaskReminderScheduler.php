<?php

namespace App\Services\Reminders;

use App\Models\Task;
use App\Models\TaskReminder;
use App\Jobs\SendTelegramTaskReminderJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskReminderScheduler
{
    public function __construct(
        private readonly NotificationTimeAgent $agent
    ) {}

    public function reschedule(Task $task, string $channel = 'telegram', bool $continueFromLastSent = false): void
    {
        Log::channel('reminders')->info('Reschedule start', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'channel' => $channel,
            'completed' => (bool) $task->completed,
            'has_reminder_text' => trim((string) ($task->reminder_text ?? '')) !== '',
            'continue_from_last_sent' => $continueFromLastSent,
        ]);

        if ($task->completed) {
            $this->cancelPending($task, $channel);
            return;
        }

        $text = trim((string) ($task->reminder_text ?? ''));
        if ($text === '') {
            $this->cancelPending($task, $channel);
            return;
        }

        // Определяем базовое время для AI агента:
        // 1. Если continueFromLastSent=true (автопродление) — используем время последнего отправленного уведомления
        // 2. Если задача только что создана (в пределах 1 минуты) — используем created_at
        // 3. Иначе — используем now() (чтобы новые уведомления были в будущем)
        if ($continueFromLastSent) {
            $lastSent = TaskReminder::where('task_id', $task->id)
                ->where('channel', $channel)
                ->where('status', TaskReminder::STATUS_SENT)
                ->whereNotNull('sent_at')
                ->orderBy('sent_at', 'desc')
                ->first();

            $createdAt = $lastSent && $lastSent->sent_at
                ? $lastSent->sent_at->copy()
                : now();
        } else {
            $createdAt = ($task->created_at && $task->created_at->diffInMinutes(now()) < 1)
                ? $task->created_at->copy()
                : now();
        }

        $expiresAt = method_exists($task, 'getReminderExpiresAt') ? $task->getReminderExpiresAt() : null;

        Log::channel('reminders')->debug('Compute schedule input', [
            'task_id' => $task->id,
            'created_at' => $createdAt?->format('Y-m-d H:i'),
            'expires_at' => $expiresAt?->format('Y-m-d H:i'),
            'reminder_text_preview' => mb_substr($text, 0, 300),
        ]);

        $t0 = microtime(true);
        $times = $this->agent->computeSchedule($createdAt, $text, $expiresAt);
        Log::channel('reminders')->info('Compute schedule done', [
            'task_id' => $task->id,
            'duration_ms' => (int) ((microtime(true) - $t0) * 1000),
            'times_count' => count($times),
            'times_preview' => array_map(fn ($t) => $t->format('Y-m-d H:i'), array_slice($times, 0, 5)),
        ]);

        /** @var array<int,TaskReminder> $created */
        $created = [];
        $cancelledCount = 0;

        DB::transaction(function () use ($task, $channel, $text, $times, &$created, &$cancelledCount) {
            $cancelledCount = TaskReminder::where('task_id', $task->id)
                ->where('channel', $channel)
                ->where('status', TaskReminder::STATUS_PENDING)
                ->update([
                    'status' => TaskReminder::STATUS_CANCELLED,
                    'updated_at' => now(),
                ]);

            $now = now();
            foreach ($times as $sendAt) {
                // Пропускаем уведомления в прошлом или слишком близко к текущему времени
                // (минимум +1 минута, чтобы избежать бесконечного цикла)
                if ($sendAt->isPast() || $sendAt->diffInMinutes($now) < 1) {
                    Log::channel('reminders')->warning('Skipping reminder in past or too soon', [
                        'task_id' => $task->id,
                        'send_at' => $sendAt->format('Y-m-d H:i:s'),
                        'now' => $now->format('Y-m-d H:i:s'),
                        'diff_minutes' => $sendAt->diffInMinutes($now),
                    ]);
                    continue;
                }

                $created[] = TaskReminder::create([
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                    'channel' => $channel,
                    'send_at' => $sendAt,
                    'status' => TaskReminder::STATUS_PENDING,
                    'source_text' => $text,
                ]);
            }
        });

        Log::channel('reminders')->info('DB reminders updated', [
            'task_id' => $task->id,
            'cancelled_pending' => $cancelledCount,
            'created' => count($created),
            'created_ids_preview' => array_slice(array_map(fn ($r) => $r->id, $created), 0, 10),
        ]);

        $dispatched = 0;
        $now = now();
        foreach ($created as $reminder) {
            // Диспатчим только уведомления, которые должны быть отправлены минимум через 1 минуту
            // Это предотвращает создание уведомлений, которые отправятся сразу и вызовут бесконечный цикл
            if ($reminder->send_at && $reminder->send_at->isFuture() && $reminder->send_at->diffInMinutes($now) >= 1) {
                SendTelegramTaskReminderJob::dispatch($reminder->id)->delay($reminder->send_at);
                $dispatched++;
            } else {
                Log::channel('reminders')->warning('Reminder skipped (too soon or in past)', [
                    'task_id' => $task->id,
                    'reminder_id' => $reminder->id,
                    'send_at' => $reminder->send_at?->format('Y-m-d H:i:s'),
                    'now' => $now->format('Y-m-d H:i:s'),
                    'diff_minutes' => $reminder->send_at ? $reminder->send_at->diffInMinutes($now) : null,
                ]);
            }
        }

        Log::channel('reminders')->info('Reschedule finish', [
            'task_id' => $task->id,
            'created_total' => count($created),
            'jobs_dispatched' => $dispatched,
        ]);
    }

    public function cancelPending(Task $task, string $channel = 'telegram'): void
    {
        $count = TaskReminder::where('task_id', $task->id)
            ->where('channel', $channel)
            ->where('status', TaskReminder::STATUS_PENDING)
            ->update([
                'status' => TaskReminder::STATUS_CANCELLED,
                'updated_at' => now(),
            ]);

        Log::channel('reminders')->info('Cancel pending reminders', [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'channel' => $channel,
            'cancelled' => $count,
        ]);
    }
}


