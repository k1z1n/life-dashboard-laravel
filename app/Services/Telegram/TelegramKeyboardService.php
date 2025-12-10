<?php

namespace App\Services\Telegram;

use App\Models\Task;
use App\Models\Project;
use App\Models\Priority;

/**
 * Сервис для создания клавиатур Telegram
 * Reply Keyboard — постоянные кнопки внизу экрана
 * Inline Keyboard — кнопки под сообщениями
 */
class TelegramKeyboardService
{
    /**
     * Главное меню (Reply Keyboard) — постоянные кнопки внизу
     */
    public function getMainMenu(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Мои задачи'],
                    ['text' => TelegramIcons::TODAY . ' Сегодня'],
                ],
                [
                    ['text' => TelegramIcons::TASK_NEW . ' Создать задачу'],
                    ['text' => TelegramIcons::OVERDUE . ' Просрочено'],
                ],
                [
                    ['text' => TelegramIcons::PROJECT . ' Проекты'],
                    ['text' => TelegramIcons::STATS . ' Статистика'],
                ],
                [
                    ['text' => TelegramIcons::SETTINGS . ' Настройки'],
                    ['text' => TelegramIcons::HELP . ' Помощь'],
                ],
            ],
            'resize_keyboard' => true,
            'persistent' => true,
        ];
    }

    /**
     * Inline меню быстрых действий
     */
    public function getQuickActionsInline(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Задачи', 'callback_data' => 'menu_tasks'],
                    ['text' => TelegramIcons::TODAY . ' Сегодня', 'callback_data' => 'menu_today'],
                ],
                [
                    ['text' => TelegramIcons::TASK_NEW . ' Создать', 'callback_data' => 'menu_add'],
                    ['text' => TelegramIcons::STATS . ' Профиль', 'callback_data' => 'menu_profile'],
                ],
            ],
        ];
    }

    /**
     * Inline кнопки для списка задач
     */
    public function getTasksListInline(string $currentFilter = 'active'): array
    {
        $buttons = [
            [
                [
                    'text' => ($currentFilter === 'active' ? '• ' : '') . 'Активные',
                    'callback_data' => 'filter_active'
                ],
                [
                    'text' => ($currentFilter === 'today' ? '• ' : '') . 'Сегодня',
                    'callback_data' => 'filter_today'
                ],
            ],
            [
                [
                    'text' => ($currentFilter === 'completed' ? '• ' : '') . 'Выполненные',
                    'callback_data' => 'filter_completed'
                ],
                [
                    'text' => ($currentFilter === 'overdue' ? '• ' : '') . 'Просрочено',
                    'callback_data' => 'filter_overdue'
                ],
            ],
            [
                ['text' => TelegramIcons::TASK_NEW . ' Создать задачу', 'callback_data' => 'menu_add'],
            ],
            [
                ['text' => TelegramIcons::REFRESH . ' Обновить', 'callback_data' => 'refresh_tasks'],
            ],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Inline кнопки для конкретной задачи в списке
     */
    public function getTaskRowButtons(Task $task): array
    {
        $completeBtn = $task->completed
            ? ['text' => TelegramIcons::BACK . ' Вернуть', 'callback_data' => "task_uncomplete_{$task->id}"]
            : ['text' => TelegramIcons::TASK_DONE . ' Готово', 'callback_data' => "task_complete_{$task->id}"];

        return [
            $completeBtn,
            ['text' => TelegramIcons::INFO . ' Детали', 'callback_data' => "task_details_{$task->id}"],
        ];
    }

    /**
     * Inline кнопки для детального просмотра задачи
     */
    public function getTaskDetailsInline(Task $task): array
    {
        $buttons = [];

        // Кнопка выполнения
        $buttons[] = [
            $task->completed
                ? ['text' => TelegramIcons::BACK . ' Вернуть в работу', 'callback_data' => "task_uncomplete_{$task->id}"]
                : ['text' => TelegramIcons::TASK_DONE . ' Отметить выполненной', 'callback_data' => "task_complete_{$task->id}"],
        ];

        // Редактирование
        $buttons[] = [
            ['text' => TelegramIcons::TASK_EDIT . ' Изменить', 'callback_data' => "task_edit_{$task->id}"],
            ['text' => TelegramIcons::CALENDAR . ' Срок', 'callback_data' => "task_setdate_{$task->id}"],
        ];

        // Проект и приоритет
        $buttons[] = [
            ['text' => TelegramIcons::PROJECT . ' Проект', 'callback_data' => "task_setproject_{$task->id}"],
            ['text' => TelegramIcons::PRIORITY . ' Приоритет', 'callback_data' => "task_setpriority_{$task->id}"],
        ];

        // Удаление и назад
        $buttons[] = [
            ['text' => TelegramIcons::TASK_DELETE . ' Удалить', 'callback_data' => "task_confirmdelete_{$task->id}"],
            ['text' => TelegramIcons::BACK . ' К списку', 'callback_data' => 'back_tasks'],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Подтверждение удаления задачи
     */
    public function getDeleteConfirmInline(int $taskId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::ERROR . ' Да, удалить', 'callback_data' => "task_delete_{$taskId}"],
                    ['text' => TelegramIcons::BACK . ' Отмена', 'callback_data' => "task_details_{$taskId}"],
                ],
            ],
        ];
    }

    /**
     * Inline кнопки выбора приоритета
     */
    public function getPrioritySelectInline(int $taskId, ?int $currentPriorityId = null): array
    {
        $priorities = Priority::orderBy('order', 'desc')->get();
        $buttons = [];

        foreach ($priorities as $priority) {
            $icon = TelegramIcons::getPriorityIcon($priority->order);
            $selected = $priority->id === $currentPriorityId ? ' •' : '';
            $buttons[] = [
                ['text' => "{$icon} {$priority->name}{$selected}", 'callback_data' => "setpriority_{$taskId}_{$priority->id}"],
            ];
        }

        $buttons[] = [
            ['text' => TelegramIcons::PRIORITY_NONE . ' Без приоритета', 'callback_data' => "setpriority_{$taskId}_0"],
        ];

        $buttons[] = [
            ['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => "task_details_{$taskId}"],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Inline кнопки выбора проекта
     */
    public function getProjectSelectInline(int $taskId, int $userId, ?int $currentProjectId = null): array
    {
        $projects = Project::where('user_id', $userId)->orderBy('name')->get();
        $buttons = [];

        foreach ($projects->take(8) as $project) {
            $selected = $project->id === $currentProjectId ? ' •' : '';
            $buttons[] = [
                ['text' => TelegramIcons::PROJECT . " {$project->name}{$selected}", 'callback_data' => "setproject_{$taskId}_{$project->id}"],
            ];
        }

        $buttons[] = [
            ['text' => TelegramIcons::INBOX . ' Входящие (без проекта)', 'callback_data' => "setproject_{$taskId}_0"],
        ];

        $buttons[] = [
            ['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => "task_details_{$taskId}"],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Inline кнопки выбора даты
     */
    public function getDateSelectInline(int $taskId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TODAY . ' Сегодня', 'callback_data' => "setdate_{$taskId}_today"],
                    ['text' => TelegramIcons::TOMORROW . ' Завтра', 'callback_data' => "setdate_{$taskId}_tomorrow"],
                ],
                [
                    ['text' => TelegramIcons::WEEK . ' Через неделю', 'callback_data' => "setdate_{$taskId}_week"],
                    ['text' => TelegramIcons::CALENDAR . ' Без срока', 'callback_data' => "setdate_{$taskId}_none"],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => "task_details_{$taskId}"],
                ],
            ],
        ];
    }

    /**
     * Inline кнопки для создания задачи (после ввода названия)
     */
    public function getNewTaskOptionsInline(string $sessionId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_DONE . ' Создать сейчас', 'callback_data' => "newtask_create_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::PRIORITY . ' Приоритет', 'callback_data' => "newtask_priority_{$sessionId}"],
                    ['text' => TelegramIcons::PROJECT . ' Проект', 'callback_data' => "newtask_project_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::CALENDAR . ' Срок', 'callback_data' => "newtask_date_{$sessionId}"],
                    ['text' => TelegramIcons::TASK . ' Описание', 'callback_data' => "newtask_desc_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::ERROR . ' Отмена', 'callback_data' => 'newtask_cancel'],
                ],
            ],
        ];
    }

    /**
     * Inline кнопки статистики
     */
    public function getProfileInline(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Мои задачи', 'callback_data' => 'menu_tasks'],
                    ['text' => TelegramIcons::TODAY . ' Сегодня', 'callback_data' => 'menu_today'],
                ],
                [
                    ['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')],
                ],
                [
                    ['text' => TelegramIcons::REFRESH . ' Обновить', 'callback_data' => 'refresh_profile'],
                ],
            ],
        ];
    }

    /**
     * Inline кнопки для списка проектов
     */
    public function getProjectsListInline(int $userId): array
    {
        $projects = Project::where('user_id', $userId)
            ->withCount(['tasks as active_tasks_count' => function ($q) {
                $q->where('completed', false);
            }])
            ->orderBy('name')
            ->get();

        $buttons = [];

        foreach ($projects->take(10) as $project) {
            $count = $project->active_tasks_count > 0 ? " ({$project->active_tasks_count})" : '';
            $buttons[] = [
                ['text' => TelegramIcons::PROJECT . " {$project->name}{$count}", 'callback_data' => "project_tasks_{$project->id}"],
            ];
        }

        $buttons[] = [
            ['text' => TelegramIcons::INBOX . ' Входящие (без проекта)', 'callback_data' => 'project_tasks_0'],
        ];

        $buttons[] = [
            ['text' => TelegramIcons::PROJECT_NEW . ' Создать проект', 'callback_data' => 'project_new'],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Настройки пользователя
     */
    public function getSettingsInline(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::BELL . ' Уведомления', 'callback_data' => 'settings_notifications'],
                ],
                [
                    ['text' => TelegramIcons::UNLINK . ' Отвязать аккаунт', 'callback_data' => 'settings_unlink'],
                ],
                [
                    ['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => 'menu_main'],
                ],
            ],
        ];
    }

    /**
     * Помощь
     */
    public function getHelpInline(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Как работать с задачами?', 'callback_data' => 'help_tasks'],
                ],
                [
                    ['text' => TelegramIcons::PROJECT . ' Как создать проект?', 'callback_data' => 'help_projects'],
                ],
                [
                    ['text' => TelegramIcons::LINK . ' Как привязать аккаунт?', 'callback_data' => 'help_link'],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' Главное меню', 'callback_data' => 'menu_main'],
                ],
            ],
        ];
    }

    /**
     * Убрать клавиатуру
     */
    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }
}

