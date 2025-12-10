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
        $icon = TelegramIcons::getTaskStatusIcon($task->completed);
        $title = $task->completed ?
            "<s>{$task->title}</s>" :
            "<b>{$task->title}</b>";

        $message = "{$icon} {$title}\n";
        $message .= "<code>ID: {$task->id}</code>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        if ($detailed && $task->description) {
            $message .= TelegramIcons::TASK . " <b>Описание:</b>\n{$task->description}\n\n";
        }

        if ($task->project) {
            $message .= TelegramIcons::PROJECT . " Проект: <b>{$task->project->name}</b>\n";
        }

        if ($task->priority) {
            $priorityIcon = TelegramIcons::getPriorityIcon($task->priority->order);
            $message .= "{$priorityIcon} Приоритет: <b>{$task->priority->name}</b>\n";
        }

        if ($task->due_date) {
            $date = $task->due_date->locale('ru')->isoFormat('D MMMM YYYY');
            $time = $task->due_time ? " в {$task->due_time}" : '';

            // Проверяем просрочено ли
            if (!$task->completed && $task->due_date->isPast()) {
                $message .= TelegramIcons::OVERDUE . " Срок: <b>{$date}{$time}</b> (просрочено)\n";
            } elseif ($task->due_date->isToday()) {
                $message .= TelegramIcons::TODAY . " Срок: <b>Сегодня</b>{$time}\n";
            } elseif ($task->due_date->isTomorrow()) {
                $message .= TelegramIcons::TOMORROW . " Срок: <b>Завтра</b>{$time}\n";
            } else {
                $message .= TelegramIcons::CALENDAR . " Срок: <b>{$date}{$time}</b>\n";
            }
        }

        if ($task->tags->isNotEmpty()) {
            $tags = $task->tags->pluck('name')->implode(', ');
            $message .= TelegramIcons::TAG . " Теги: {$tags}\n";
        }

        if ($task->completed && $task->completed_at) {
            $completedDate = $task->completed_at->locale('ru')->isoFormat('D MMMM, HH:mm');
            $message .= "\n" . TelegramIcons::SUCCESS . " <i>Выполнено: {$completedDate}</i>";
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
                'text' => TelegramIcons::TASK_LIST . " <b>{$title}</b>\n\n" .
                          TelegramIcons::SPARKLE . " <i>Задач не найдено.</i>\n\n" .
                          "Создайте новую задачу!",
                'keyboard' => null,
            ];
        }

        $message = TelegramIcons::TASK_LIST . " <b>{$title}</b> ({$tasks->count()})\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        $keyboard = [];

        foreach ($tasks->take(10) as $index => $task) {
            $num = $index + 1;
            $icon = TelegramIcons::getTaskStatusIcon($task->completed);
            $taskTitle = $task->completed ? "~{$task->title}~" : $task->title;

            $priorityIcon = '';
            if ($task->priority) {
                $priorityIcon = TelegramIcons::getPriorityIcon($task->priority->order) . ' ';
            }

            // Информация о проекте и дате
            $meta = [];
            if ($task->project) {
                $meta[] = $task->project->name;
            }
            if ($task->due_date) {
                if ($task->due_date->isToday()) {
                    $meta[] = 'сегодня';
                } elseif ($task->due_date->isTomorrow()) {
                    $meta[] = 'завтра';
                } elseif (!$task->completed && $task->due_date->isPast()) {
                    $meta[] = TelegramIcons::OVERDUE;
                } else {
                    $meta[] = $task->due_date->format('d.m');
                }
            }

            $metaStr = $meta ? ' • ' . implode(' • ', $meta) : '';

            $message .= "<b>{$num}.</b> {$icon} {$priorityIcon}{$taskTitle}{$metaStr}\n";

            // Inline keyboard buttons for each task
            $keyboard[] = [
                [
                    'text' => $task->completed ? TelegramIcons::BACK . ' Вернуть' : TelegramIcons::TASK_DONE,
                    'callback_data' => $task->completed ? "task_uncomplete_{$task->id}" : "task_complete_{$task->id}"
                ],
                [
                    'text' => TelegramIcons::INFO . ' Детали',
                    'callback_data' => "task_details_{$task->id}"
                ],
            ];
        }

        if ($tasks->count() > 10) {
            $message .= "\n<i>... и еще " . ($tasks->count() - 10) . " задач</i>";
        }

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
                'text' => $task->completed
                    ? TelegramIcons::BACK . ' Вернуть в работу'
                    : TelegramIcons::TASK_DONE . ' Выполнить',
                'callback_data' => $task->completed
                    ? "task_uncomplete_{$task->id}"
                    : "task_complete_{$task->id}"
            ],
        ];

        // Edit, Project, Priority, Due Date buttons
        $keyboard[] = [
            ['text' => TelegramIcons::TASK_EDIT . ' Изменить', 'callback_data' => "task_edit_{$task->id}"],
            ['text' => TelegramIcons::PROJECT . ' Проект', 'callback_data' => "task_setproject_{$task->id}"],
        ];

        $keyboard[] = [
            ['text' => TelegramIcons::PRIORITY . ' Приоритет', 'callback_data' => "task_setpriority_{$task->id}"],
            ['text' => TelegramIcons::CALENDAR . ' Срок', 'callback_data' => "task_setdate_{$task->id}"],
        ];

        // Delete and Back buttons
        $keyboard[] = [
            ['text' => TelegramIcons::TASK_DELETE . ' Удалить', 'callback_data' => "task_confirmdelete_{$task->id}"],
            ['text' => TelegramIcons::BACK . ' К списку', 'callback_data' => 'back_tasks'],
        ];

        return $keyboard;
    }
}
