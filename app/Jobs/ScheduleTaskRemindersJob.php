<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\UserNotification;
use App\Services\Reminders\TaskReminderScheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduleTaskRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [30, 120];
    public $timeout = 120;

    public function __construct(
        public readonly int $taskId,
        public readonly string $channel = 'telegram',
        public readonly bool $continueFromLastSent = false
    ) {}

    public function handle(TaskReminderScheduler $scheduler): void
    {
        $task = Task::find($this->taskId);

        if (!$task) {
            Log::channel('reminders')->warning('ScheduleTaskRemindersJob: task not found', [
                'task_id' => $this->taskId,
            ]);
            return;
        }

        try {
            $scheduler->reschedule($task, $this->channel, $this->continueFromLastSent);
        } catch (\Throwable $e) {
            Log::channel('reminders')->error('ScheduleTaskRemindersJob failed', [
                'task_id' => $this->taskId,
                'user_id' => $task->user_id,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            // Создаём веб-уведомление пользователю об ошибке
            try {
                UserNotification::create([
                    'user_id' => $task->user_id,
                    'type' => 'reminder_error',
                    'title' => 'Ошибка создания напоминаний',
                    'body' => "Не удалось создать напоминания для задачи «{$task->title}». Проверьте текст напоминаний и попробуйте сохранить задачу снова.",
                    'url' => route('dashboard'),
                    'data' => [
                        'task_id' => $task->id,
                        'task_title' => $task->title,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ],
                ]);
            } catch (\Throwable $notifError) {
                Log::channel('reminders')->error('Failed to create error notification', [
                    'task_id' => $this->taskId,
                    'error' => $notifError->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
