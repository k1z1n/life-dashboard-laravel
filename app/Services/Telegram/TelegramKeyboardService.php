<?php

namespace App\Services\Telegram;

use App\Models\Task;
use App\Models\Project;
use App\Models\Priority;

/**
 * Сервис для создания контекстных клавиатур Telegram
 * 
 * Reply Keyboard — постоянные кнопки внизу экрана (меняются по контексту)
 * Inline Keyboard — кнопки под сообщениями
 */
class TelegramKeyboardService
{
    // ═══════════════════════════════════════════════════════════════
    // REPLY KEYBOARDS (кнопки внизу экрана)
    // ═══════════════════════════════════════════════════════════════

    /**
     * 🏠 ГЛАВНОЕ МЕНЮ
     * Показывается после /start, /menu или возврата в меню
     */
    public function getMainMenuKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Мои задачи'],
                    ['text' => TelegramIcons::TODAY . ' Сегодня'],
                ],
                [
                    ['text' => TelegramIcons::TASK_NEW . ' Создать'],
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
     * 📋 СПИСОК ЗАДАЧ
     * Показывается при просмотре списка задач
     */
    public function getTasksListKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Все'],
                    ['text' => TelegramIcons::TODAY . ' Сегодня'],
                    ['text' => TelegramIcons::TASK_DONE . ' Готово'],
                ],
                [
                    ['text' => TelegramIcons::TASK_NEW . ' Создать'],
                    ['text' => TelegramIcons::REFRESH . ' Обновить'],
                ],
                [
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * ➕ СОЗДАНИЕ ЗАДАЧИ
     * Показывается когда пользователь создаёт задачу
     */
    public function getCreateTaskKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::ERROR . ' Отмена'],
                ],
                [
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * 📝 ДЕТАЛИ ЗАДАЧИ
     * Показывается при просмотре конкретной задачи
     */
    public function getTaskDetailsKeyboard(bool $isCompleted = false): array
    {
        $completeBtn = $isCompleted 
            ? ['text' => TelegramIcons::BACK . ' Вернуть']
            : ['text' => TelegramIcons::TASK_DONE . ' Выполнить'];

        return [
            'keyboard' => [
                [
                    $completeBtn,
                    ['text' => TelegramIcons::TASK_DELETE . ' Удалить'],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' К списку'],
                    ['text' => TelegramIcons::HOME . ' Меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * 📁 ПРОЕКТЫ
     * Показывается при просмотре проектов
     */
    public function getProjectsKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::REFRESH . ' Обновить'],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' Назад'],
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * 📊 СТАТИСТИКА / ПРОФИЛЬ
     */
    public function getProfileKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Мои задачи'],
                    ['text' => TelegramIcons::REFRESH . ' Обновить'],
                ],
                [
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * ⚙️ НАСТРОЙКИ
     */
    public function getSettingsKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::BACK . ' Назад'],
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * ❓ СПРАВКА
     */
    public function getHelpKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_LIST . ' Мои задачи'],
                    ['text' => TelegramIcons::TASK_NEW . ' Создать'],
                ],
                [
                    ['text' => TelegramIcons::HOME . ' Главное меню'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * ⚠️ ПОДТВЕРЖДЕНИЕ (удаление и т.п.)
     */
    public function getConfirmKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => TelegramIcons::SUCCESS . ' Да'],
                    ['text' => TelegramIcons::ERROR . ' Нет'],
                ],
                [
                    ['text' => TelegramIcons::BACK . ' Отмена'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * Убрать клавиатуру
     */
    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    // ═══════════════════════════════════════════════════════════════
    // INLINE KEYBOARDS (кнопки под сообщениями)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Inline меню быстрых действий (под приветствием)
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
     * Inline фильтры для списка задач
     */
    public function getTasksFiltersInline(string $currentFilter = 'active'): array
    {
        $filters = [
            'active' => 'Активные',
            'today' => 'Сегодня',
            'completed' => 'Готово',
            'overdue' => 'Просрочено',
        ];

        $buttons = [];
        $row = [];
        foreach ($filters as $key => $label) {
            $text = ($currentFilter === $key ? '• ' : '') . $label;
            $row[] = ['text' => $text, 'callback_data' => "filter_{$key}"];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }

        $buttons[] = [
            ['text' => TelegramIcons::TASK_NEW . ' Создать задачу', 'callback_data' => 'menu_add'],
        ];
        $buttons[] = [
            ['text' => TelegramIcons::REFRESH . ' Обновить', 'callback_data' => 'refresh_tasks'],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Inline кнопки для конкретной задачи в списке
     */
    public function getTaskRowInline(Task $task): array
    {
        $completeBtn = $task->completed
            ? ['text' => TelegramIcons::BACK, 'callback_data' => "task_uncomplete_{$task->id}"]
            : ['text' => TelegramIcons::TASK_DONE, 'callback_data' => "task_complete_{$task->id}"];

        return [
            $completeBtn,
            ['text' => TelegramIcons::INFO, 'callback_data' => "task_details_{$task->id}"],
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
                : ['text' => TelegramIcons::TASK_DONE . ' Выполнить', 'callback_data' => "task_complete_{$task->id}"],
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
     * Подтверждение удаления задачи (inline)
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
     * Inline выбор приоритета
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
     * Inline выбор проекта
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
     * Inline выбор даты
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
     * Inline для создания задачи (после ввода названия)
     */
    public function getNewTaskOptionsInline(string $sessionId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_DONE . ' Создать', 'callback_data' => "newtask_create_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::PRIORITY . ' Приоритет', 'callback_data' => "newtask_priority_{$sessionId}"],
                    ['text' => TelegramIcons::PROJECT . ' Проект', 'callback_data' => "newtask_project_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::CALENDAR . ' Срок', 'callback_data' => "newtask_date_{$sessionId}"],
                ],
                [
                    ['text' => TelegramIcons::ERROR . ' Отмена', 'callback_data' => 'newtask_cancel'],
                ],
            ],
        ];
    }

    /**
     * Inline для статистики/профиля
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
     * Inline для списка проектов
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

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Inline для настроек
     */
    public function getSettingsInline(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::UNLINK . ' Отвязать аккаунт', 'callback_data' => 'settings_unlink'],
                ],
                [
                    ['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')],
                ],
            ],
        ];
    }

    /**
     * Inline для справки
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
            ],
        ];
    }
}
