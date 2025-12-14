<?php

namespace App\Jobs;

use App\Models\TaskReminder;
use App\Models\TelegramUser;
use App\Models\UserNotification;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramIcons;
use App\Services\Reminders\TaskReminderScheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramTaskReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];
    public $timeout = 60;

    public function __construct(
        public readonly int $taskReminderId
    ) {}

    public function handle(TelegramBotService $botService, TaskReminderScheduler $scheduler): void
    {
        Log::channel('reminders')->info('SendTelegramTaskReminderJob start', [
            'task_reminder_id' => $this->taskReminderId,
        ]);

        $reminder = TaskReminder::with(['task', 'user'])->find($this->taskReminderId);

        if (!$reminder) {
            Log::channel('reminders')->warning('Reminder not found', [
                'task_reminder_id' => $this->taskReminderId,
            ]);
            return;
        }

        if ($reminder->status !== TaskReminder::STATUS_PENDING) {
            Log::channel('reminders')->info('Reminder not pending, skip', [
                'task_reminder_id' => $reminder->id,
                'status' => $reminder->status,
            ]);
            return;
        }

        if (!$reminder->task || $reminder->task->completed) {
            $reminder->update([
                'status' => TaskReminder::STATUS_CANCELLED,
            ]);
            Log::channel('reminders')->info('Reminder cancelled (task missing/completed)', [
                'task_reminder_id' => $reminder->id,
                'task_id' => $reminder->task_id,
            ]);
            return;
        }

        $telegramUser = TelegramUser::where('user_id', $reminder->user_id)
            ->where('is_active', true)
            ->first();

        if (!$telegramUser) {
            $reminder->update([
                'status' => TaskReminder::STATUS_CANCELLED,
                'error' => 'telegram_not_linked',
            ]);
            Log::channel('reminders')->warning('Reminder cancelled (telegram not linked)', [
                'task_reminder_id' => $reminder->id,
                'user_id' => $reminder->user_id,
            ]);
            return;
        }

        $task = $reminder->task;

        $text = TelegramIcons::CLOCK . " <b>Напоминание</b>\n\n";
        $text .= TelegramIcons::TASK . " {$task->title}";
        if ($task->due_date) {
            $due = $task->getReminderExpiresAt();
            $text .= "\n\n" . TelegramIcons::CALENDAR . " Срок: <b>" . ($due?->format('Y-m-d H:i') ?? $task->due_date->format('Y-m-d')) . "</b>";
        }

        try {
            Log::channel('reminders')->info('Sending telegram reminder', [
                'task_reminder_id' => $reminder->id,
                'task_id' => $task->id,
                'user_id' => $reminder->user_id,
                'chat_id' => $telegramUser->chat_id,
            ]);

            $botService->sendMessage($telegramUser->chat_id, $text);

            $reminder->update([
                'status' => TaskReminder::STATUS_SENT,
                'sent_at' => now(),
                'error' => null,
            ]);

            // Web notification (bell)
            UserNotification::create([
                'user_id' => $reminder->user_id,
                'type' => 'task_reminder',
                'title' => 'Напоминание',
                'body' => $task->title,
                'url' => route('dashboard'),
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                ],
            ]);

            Log::channel('reminders')->info('Telegram reminder sent', [
                'task_reminder_id' => $reminder->id,
                'sent_at' => now()->toDateTimeString(),
            ]);

            // Автоматическое продление: если осталось мало pending reminders (< 5),
            // пересчитываем напоминания, чтобы продолжить серию логично.
            if ($task->reminder_text && trim($task->reminder_text) !== '') {
                $remainingPending = TaskReminder::where('task_id', $task->id)
                    ->where('channel', $reminder->channel)
                    ->where('status', TaskReminder::STATUS_PENDING)
                    ->where('send_at', '>', now())
                    ->count();

                if ($remainingPending < 5) {
                    // Проверяем, не изменился ли reminder_text с момента последнего расчёта.
                    // Если изменился — это ручное изменение пользователем, нужно пересчитать от now().
                    // Если не изменился — продолжаем серию от last_sent_at.
                    $currentText = trim((string) $task->reminder_text);
                    $lastSentWithSameText = TaskReminder::where('task_id', $task->id)
                        ->where('channel', $reminder->channel)
                        ->where('status', TaskReminder::STATUS_SENT)
                        ->where('source_text', $currentText)
                        ->whereNotNull('sent_at')
                        ->orderBy('sent_at', 'desc')
                        ->first();

                    $shouldContinueFromLastSent = $lastSentWithSameText && $lastSentWithSameText->sent_at;

                    Log::channel('reminders')->info('Auto-extending reminders (low pending count)', [
                        'task_id' => $task->id,
                        'remaining_pending' => $remainingPending,
                        'last_sent_at' => $reminder->sent_at?->format('Y-m-d H:i'),
                        'text_changed' => !$shouldContinueFromLastSent,
                        'continue_from_last_sent' => $shouldContinueFromLastSent,
                    ]);

                    try {
                        // Если reminder_text не менялся — продолжаем серию от последнего отправленного
                        // Если изменился — пересчитываем от now() (пользователь изменил условия)
                        $scheduler->reschedule($task, $reminder->channel, continueFromLastSent: $shouldContinueFromLastSent);
                    } catch (\Throwable $e) {
                        Log::channel('reminders')->warning('Auto-extend failed', [
                            'task_id' => $task->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('telegram')->error('Failed to send task reminder', [
                'task_reminder_id' => $reminder->id,
                'task_id' => $reminder->task_id,
                'user_id' => $reminder->user_id,
                'error' => $e->getMessage(),
            ]);

            $reminder->update([
                'status' => TaskReminder::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            Log::channel('reminders')->error('Telegram reminder failed', [
                'task_reminder_id' => $reminder->id,
                'task_id' => $reminder->task_id,
                'user_id' => $reminder->user_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}


