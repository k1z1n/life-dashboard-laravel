<?php

namespace App\Services\Telegram;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class TelegramTaskService
{
    /**
     * Get tasks list for user
     */
    public function getTasksList(User $user, string $filter = 'all'): Collection
    {
        $query = Task::where('user_id', $user->id)
            ->with(['priority', 'project', 'tags']);

        switch ($filter) {
            case 'today':
                $query->where(function ($q) {
                    $q->where('completed', false)
                        ->orWhere(function ($subQ) {
                            $subQ->where('completed', true)
                                ->whereDate('completed_at', today());
                        });
                })->whereDate('due_date', today());
                break;

            case 'overdue':
                $query->where('completed', false)
                    ->whereDate('due_date', '<', today());
                break;

            case 'completed':
                $query->where('completed', true)
                    ->whereDate('completed_at', today());
                break;

            case 'active':
            default:
                $query->where(function ($q) {
                    $q->where('completed', false)
                        ->orWhere(function ($subQ) {
                            $subQ->where('completed', true)
                                ->whereDate('completed_at', '>=', now()->startOfDay());
                        });
                });
                break;
        }

        return $query->orderBy('completed')
            ->orderBy('order')
            ->orderByRaw('(SELECT `order` FROM priorities WHERE priorities.id = tasks.priority_id) DESC')
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Format task for Telegram message
     */
    public function formatTaskMessage(Task $task, bool $detailed = false): string
    {
        $icon = $task->completed ? '✅' : '⬜';
        $title = $task->completed ?
            "<s>{$task->title}</s>" :
            "<b>{$task->title}</b>";

        $message = "{$icon} {$title}\n";
        $message .= "ID: {$task->id}\n\n";

        if ($detailed && $task->description) {
            $message .= "📝 Описание:\n{$task->description}\n\n";
        }

        if ($task->project) {
            $message .= "📁 Проект: {$task->project->name}\n";
        }

        if ($task->priority) {
            $priorityIcon = match($task->priority->order) {
                3 => '🔴',
                2 => '🟡',
                1 => '🟢',
                default => '⚪'
            };
            $message .= "{$priorityIcon} Приоритет: {$task->priority->name}\n";
        }

        if ($task->due_date) {
            $date = $task->due_date->locale('ru')->isoFormat('D MMMM YYYY');
            $time = $task->due_time ? " в {$task->due_time}" : '';
            $message .= "📅 Срок: {$date}{$time}\n";
        }

        if ($task->tags->isNotEmpty()) {
            $tags = $task->tags->pluck('name')->implode(', ');
            $message .= "🏷️ Теги: {$tags}\n";
        }

        if ($task->completed && $task->completed_at) {
            $completedDate = $task->completed_at->locale('ru')->isoFormat('D MMMM, HH:mm');
            $message .= "\n✓ Выполнено: {$completedDate}";
        }

        return $message;
    }

    /**
     * Format tasks list for Telegram message
     */
    public function formatTasksList(Collection $tasks, string $title = 'Задачи'): array
    {
        if ($tasks->isEmpty()) {
            return [
                'text' => "📋 {$title}\n\nЗадач не найдено.",
                'keyboard' => null,
            ];
        }

        $message = "📋 {$title} ({$tasks->count()})\n\n";
        $keyboard = [];

        foreach ($tasks->take(10) as $index => $task) {
            $num = $index + 1;
            $icon = $task->completed ? '✅' : '⬜';
            $title = $task->completed ? "~~{$task->title}~~" : $task->title;

            $priorityIcon = '';
            if ($task->priority) {
                $priorityIcon = match($task->priority->order) {
                    3 => '🔴',
                    2 => '🟡',
                    1 => '🟢',
                    default => ''
                };
            }

            $project = $task->project ? " | 📁 {$task->project->name}" : '';
            $date = $task->due_date ? " | 📅 " . $task->due_date->format('d.m') : '';

            $message .= "{$num}. {$icon} {$priorityIcon} {$title}{$project}{$date}\n";

            // Inline keyboard buttons for each task
            $keyboard[] = [
                [
                    'text' => $task->completed ? '↩️ Отменить' : '✅ Выполнить',
                    'callback_data' => $task->completed ? "task_uncomplete_{$task->id}" : "task_complete_{$task->id}"
                ],
                [
                    'text' => 'ℹ️ Детали',
                    'callback_data' => "task_details_{$task->id}"
                ],
            ];
        }

        if ($tasks->count() > 10) {
            $message .= "\n... и еще " . ($tasks->count() - 10) . " задач";
        }

        $keyboard[] = [
            ['text' => '🔄 Обновить', 'callback_data' => 'refresh_tasks'],
        ];

        return [
            'text' => $message,
            'keyboard' => $keyboard,
        ];
    }

    /**
     * Create inline keyboard for task actions
     */
    public function getTaskActionsKeyboard(Task $task): array
    {
        $keyboard = [];

        // Complete/Uncomplete button
        $keyboard[] = [
            [
                'text' => $task->completed ? '↩️ Отменить выполнение' : '✅ Выполнить',
                'callback_data' => $task->completed ? "task_uncomplete_{$task->id}" : "task_complete_{$task->id}"
            ],
        ];

        // Edit, Project, Priority, Due Date buttons
        $keyboard[] = [
            ['text' => '✏️ Изменить', 'callback_data' => "task_edit_{$task->id}"],
            ['text' => '📁 Проект', 'callback_data' => "task_project_{$task->id}"],
        ];

        $keyboard[] = [
            ['text' => '⚡ Приоритет', 'callback_data' => "task_priority_{$task->id}"],
            ['text' => '📅 Срок', 'callback_data' => "task_date_{$task->id}"],
        ];

        // Delete and Back buttons
        $keyboard[] = [
            ['text' => '🗑️ Удалить', 'callback_data' => "task_delete_{$task->id}"],
            ['text' => '◀️ К списку', 'callback_data' => 'back_tasks'],
        ];

        return $keyboard;
    }
}
