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
                // Задачи на сегодня: невыполненные + выполненные сегодня (та же логика что на сайте)
                $query->whereDate('due_date', today())
                    ->where(function ($q) {
                        $q->where('completed', false)
                            ->orWhere(function ($subQ) {
                                $subQ->where('completed', true)
                                    ->whereDate('completed_at', '>=', now()->startOfDay());
                            });
                    });
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
                // Та же логика что на сайте: невыполненные + выполненные сегодня
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
     * Только активные задачи, название на всю ширину, полный текст
     * С пагинацией
     */
    public function formatTasksList(Collection $tasks, string $title = 'Задачи', int $page = 1, int $perPage = 5, string $filter = 'active'): array
    {
        if ($tasks->isEmpty()) {
            return [
                'text' => TelegramIcons::TASK_LIST . " <b>{$title}</b>\n\n" .
                          TelegramIcons::SPARKLE . " <i>Задач не найдено.</i>",
                'keyboard' => null,
            ];
        }

        $total = $tasks->count();
        $totalPages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $totalPages)); // Ограничиваем page

        $offset = ($page - 1) * $perPage;
        $tasksOnPage = $tasks->slice($offset, $perPage);

        $message = TelegramIcons::TASK_LIST . " <b>{$title}</b> ({$total})";
        if ($totalPages > 1) {
            $message .= " • Страница {$page}/{$totalPages}";
        }

        $keyboard = [];

        // Для каждой задачи: название на всю ширину, потом кнопки действий
        foreach ($tasksOnPage as $task) {
            $priorityIcon = '';
            if ($task->priority) {
                $priorityIcon = TelegramIcons::getPriorityIcon($task->priority->order) . ' ';
            }

            // Иконка статуса
            $statusIcon = $task->completed ? TelegramIcons::TASK_DONE . ' ' : '';

            // Полное название задачи (макс 60 символов - это максимум для кнопки Telegram)
            $fullTitle = mb_strlen($task->title) > 60
                ? mb_substr($task->title, 0, 57) . '...'
                : $task->title;

            // Название задачи на всю ширину (с иконкой статуса для выполненных)
            $keyboard[] = [
                [
                    'text' => "{$statusIcon}{$priorityIcon}{$fullTitle}",
                    'callback_data' => "task_view_{$task->id}"
                ],
            ];

            // Кнопки действий в одной строке
            $completeBtn = $task->completed
                ? ['text' => 'Вернуть', 'callback_data' => "task_uncomplete_{$task->id}"]
                : ['text' => 'Готово', 'callback_data' => "task_complete_{$task->id}"];

            $keyboard[] = [
                $completeBtn,
                [
                    'text' => 'Детали',
                    'callback_data' => "task_details_{$task->id}"
                ],
            ];
        }

        // Пагинация
        if ($totalPages > 1) {
            $paginationRow = [];

            if ($page > 1) {
                $prevPage = $page - 1;
                $paginationRow[] = ['text' => '◀️ Назад', 'callback_data' => "page_{$filter}_{$prevPage}"];
            }

            if ($page < $totalPages) {
                $nextPage = $page + 1;
                $paginationRow[] = ['text' => 'Вперёд ▶️', 'callback_data' => "page_{$filter}_{$nextPage}"];
            }

            if (!empty($paginationRow)) {
                $keyboard[] = $paginationRow;
            }
        }

        return [
            'text' => $message,
            'keyboard' => $keyboard,
            'page' => $page,
            'total_pages' => $totalPages,
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
