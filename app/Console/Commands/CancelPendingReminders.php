<?php

namespace App\Console\Commands;

use App\Models\TaskReminder;
use Illuminate\Console\Command;

class CancelPendingReminders extends Command
{
    protected $signature = 'reminders:cancel-pending 
                            {--task-id= : Отменить уведомления только для конкретной задачи}
                            {--all : Отменить все pending уведомления}';

    protected $description = 'Отменить все pending уведомления (созданные через auto-extend)';

    public function handle(): int
    {
        $taskId = $this->option('task-id');
        $all = $this->option('all');

        if (!$taskId && !$all) {
            $this->error('Укажите --task-id=<id> или --all для отмены всех pending уведомлений');
            return Command::FAILURE;
        }

        $query = TaskReminder::where('status', TaskReminder::STATUS_PENDING);

        if ($taskId) {
            $query->where('task_id', $taskId);
            $this->info("Отмена pending уведомлений для задачи ID: {$taskId}");
        } else {
            $this->info('Отмена всех pending уведомлений...');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('Нет pending уведомлений для отмены.');
            return Command::SUCCESS;
        }

        if (!$this->confirm("Найдено {$count} pending уведомлений. Отменить их?", true)) {
            $this->info('Отменено пользователем.');
            return Command::SUCCESS;
        }

        $cancelled = $query->update([
            'status' => TaskReminder::STATUS_CANCELLED,
            'updated_at' => now(),
        ]);

        $this->info("✅ Отменено {$cancelled} pending уведомлений.");

        // Показываем статистику
        if ($taskId) {
            $stats = TaskReminder::where('task_id', $taskId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $this->info("\n📊 Статистика уведомлений для задачи {$taskId}:");
            foreach ($stats as $status => $count) {
                $this->line("  - {$status}: {$count}");
            }
        }

        return Command::SUCCESS;
    }
}
