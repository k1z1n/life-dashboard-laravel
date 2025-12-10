<?php

namespace App\Jobs;

use App\DTOs\TaskDTO;
use App\Exceptions\Telegram\TaskNotFoundException;
use App\Exceptions\Telegram\UnauthorizedException;
use App\Models\Task;
use App\Models\Project;
use App\Models\Priority;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramKeyboardService;
use App\Services\Telegram\TelegramTaskService;
use App\Services\Telegram\TelegramIcons;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\Telegram\Commands\MenuCommand;
use App\Services\TaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [5, 15];
    public $timeout = 60;

    protected TelegramKeyboardService $keyboardService;

    public function __construct(
        protected array $callbackData
    ) {}

    public function handle(
        TelegramBotService $botService,
        TelegramAuthService $authService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        $this->keyboardService = new TelegramKeyboardService();

        try {
            $callbackQueryId = $this->callbackData['id'];
            $chatId = $this->callbackData['message']['chat']['id'];
            $messageId = $this->callbackData['message']['message_id'];
            $data = $this->callbackData['data'];
            $telegramId = $this->callbackData['from']['id'];

            Log::channel('telegram')->info('Processing callback query', [
                'chat_id' => $chatId,
                'data' => $data,
            ]);

            // Проверка авторизации
            $user = $authService->getUserByTelegramId($telegramId);
            if (!$user) {
                throw new UnauthorizedException('Account not linked');
            }

            // Роутинг callback_data
            $this->routeCallback(
                $data,
                $chatId,
                $messageId,
                $callbackQueryId,
                $user,
                $botService,
                $authService,
                $telegramTaskService,
                $taskService
            );

        } catch (UnauthorizedException $e) {
            Log::channel('telegram')->warning('Unauthorized callback query', [
                'telegram_id' => $this->callbackData['from']['id'] ?? null,
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Аккаунт не привязан. Используйте /start', true);

        } catch (TaskNotFoundException $e) {
            Log::channel('telegram')->warning('Task not found in callback', [
                'error' => $e->getMessage(),
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Задача не найдена', true);

        } catch (\Exception $e) {
            Log::channel('telegram')->error('Error processing callback query', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Произошла ошибка', true);
            throw $e;
        }
    }

    /**
     * Роутинг callback_data к соответствующим обработчикам
     */
    protected function routeCallback(
        string $data,
        int $chatId,
        int $messageId,
        string $callbackQueryId,
        $user,
        TelegramBotService $botService,
        TelegramAuthService $authService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        // Парсинг callback_data
        $parts = explode('_', $data);
        $action = $parts[0];

        switch ($action) {
            // ═══════════════════════════════════════
            // МЕНЮ
            // ═══════════════════════════════════════
            case 'menu':
                $this->handleMenuCallback($parts[1] ?? '', $chatId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // ФИЛЬТРЫ ЗАДАЧ
            // ═══════════════════════════════════════
            case 'filter':
                $filter = $parts[1] ?? 'active';
                $this->showFilteredTasks($filter, $chatId, $messageId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // ДЕЙСТВИЯ С ЗАДАЧАМИ
            // ═══════════════════════════════════════
            case 'task':
                $this->handleTaskCallback(
                    $parts[1] ?? '',
                    $parts[2] ?? null,
                    $chatId,
                    $messageId,
                    $callbackQueryId,
                    $user,
                    $botService,
                    $telegramTaskService,
                    $taskService
                );
                break;

            // ═══════════════════════════════════════
            // УСТАНОВКА ПРИОРИТЕТА
            // ═══════════════════════════════════════
            case 'setpriority':
                $taskId = $parts[1] ?? null;
                $priorityId = $parts[2] ?? null;
                $this->setTaskPriority($taskId, $priorityId, $chatId, $messageId, $callbackQueryId, $user, $botService, $telegramTaskService, $taskService);
                break;

            // ═══════════════════════════════════════
            // УСТАНОВКА ПРОЕКТА
            // ═══════════════════════════════════════
            case 'setproject':
                $taskId = $parts[1] ?? null;
                $projectId = $parts[2] ?? null;
                $this->setTaskProject($taskId, $projectId, $chatId, $messageId, $callbackQueryId, $user, $botService, $telegramTaskService, $taskService);
                break;

            // ═══════════════════════════════════════
            // УСТАНОВКА ДАТЫ
            // ═══════════════════════════════════════
            case 'setdate':
                $taskId = $parts[1] ?? null;
                $dateOption = $parts[2] ?? null;
                $this->setTaskDate($taskId, $dateOption, $chatId, $messageId, $callbackQueryId, $user, $botService, $telegramTaskService, $taskService);
                break;

            // ═══════════════════════════════════════
            // ПРОЕКТЫ
            // ═══════════════════════════════════════
            case 'project':
                $this->handleProjectCallback($parts[1] ?? '', $parts[2] ?? null, $chatId, $messageId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // ОБНОВЛЕНИЕ
            // ═══════════════════════════════════════
            case 'refresh':
                $type = $parts[1] ?? 'tasks';
                $this->handleRefresh($type, $chatId, $messageId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId, 'Обновлено');
                break;

            // ═══════════════════════════════════════
            // НАЗАД
            // ═══════════════════════════════════════
            case 'back':
                $type = $parts[1] ?? 'tasks';
                $this->handleBack($type, $chatId, $messageId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // СПРАВКА
            // ═══════════════════════════════════════
            case 'help':
                $topic = $parts[1] ?? '';
                $this->handleHelpCallback($topic, $chatId, $botService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // НАСТРОЙКИ
            // ═══════════════════════════════════════
            case 'settings':
                $this->handleSettingsCallback($parts[1] ?? '', $chatId, $user, $botService, $authService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // БЫСТРОЕ СОЗДАНИЕ ЗАДАЧИ
            // ═══════════════════════════════════════
            case 'quickadd':
                $hash = $parts[1] ?? '';
                $this->handleQuickAdd($hash, $chatId, $user, $botService, $taskService);
                $botService->answerCallbackQuery($callbackQueryId, 'Задача создана!');
                break;

            // ═══════════════════════════════════════
            // НОВАЯ ЗАДАЧА
            // ═══════════════════════════════════════
            case 'newtask':
                $this->handleNewTaskCallback($parts[1] ?? '', $parts[2] ?? null, $chatId, $user, $botService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            // ═══════════════════════════════════════
            // СТАРЫЕ ФОРМАТЫ (совместимость)
            // ═══════════════════════════════════════
            case 'cmd':
                $this->handleMenuCallback($parts[1] ?? '', $chatId, $user, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            default:
                $botService->answerCallbackQuery($callbackQueryId, 'Неизвестная команда');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ОБРАБОТЧИКИ МЕНЮ
    // ═══════════════════════════════════════════════════════════════

    protected function handleMenuCallback(
        string $action,
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        switch ($action) {
            case 'main':
                $menuCommand = new MenuCommand($botService, app(TelegramAuthService::class));
                $menuCommand->showMainMenu($chatId, $user);
                break;

            case 'tasks':
                $tasks = $telegramTaskService->getTasksList($user, 'active');
                $formatted = $telegramTaskService->formatTasksList($tasks, 'Все задачи');
                $keyboard = $this->keyboardService->getTasksListInline('active');
                if ($formatted['keyboard']) {
                    $keyboard['inline_keyboard'] = array_merge($formatted['keyboard'], $keyboard['inline_keyboard']);
                }
                $botService->sendMessage($chatId, $formatted['text'], $keyboard);
                break;

            case 'today':
                $tasks = $telegramTaskService->getTasksList($user, 'today');
                $formatted = $telegramTaskService->formatTasksList($tasks, TelegramIcons::TODAY . ' Задачи на сегодня');
                $keyboard = $this->keyboardService->getTasksListInline('today');
                if ($formatted['keyboard']) {
                    $keyboard['inline_keyboard'] = array_merge($formatted['keyboard'], $keyboard['inline_keyboard']);
                }
                $botService->sendMessage($chatId, $formatted['text'], $keyboard);
                break;

            case 'add':
                $text = TelegramIcons::TASK_NEW . " <b>Создание задачи</b>\n\n";
                $text .= "Введите название новой задачи:\n\n";
                $text .= TelegramIcons::BULB . " <i>Или используйте:</i>\n";
                $text .= "<code>/add Название задачи</code>";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => TelegramIcons::ERROR . ' Отмена', 'callback_data' => 'newtask_cancel']],
                    ],
                ];
                $botService->sendMessage($chatId, $text, $keyboard);
                break;

            case 'profile':
                $this->showProfile($chatId, $user, $botService);
                break;

            case 'projects':
                $text = TelegramIcons::PROJECT . " <b>Ваши проекты</b>\n\n";
                $text .= "Выберите проект:";
                $botService->sendMessage($chatId, $text, $this->keyboardService->getProjectsListInline($user->id));
                break;

            case 'help':
                $helpCommand = new HelpCommand($botService);
                $helpCommand->sendHelp($chatId);
                break;

            case 'settings':
                $text = TelegramIcons::SETTINGS . " <b>Настройки</b>\n\n";
                $text .= "Управление аккаунтом:";
                $botService->sendMessage($chatId, $text, $this->keyboardService->getSettingsInline());
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ОБРАБОТЧИКИ ЗАДАЧ
    // ═══════════════════════════════════════════════════════════════

    protected function handleTaskCallback(
        string $action,
        ?string $taskId,
        int $chatId,
        int $messageId,
        string $callbackQueryId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        if (!$taskId) {
            $botService->answerCallbackQuery($callbackQueryId, 'ID задачи не указан', true);
            return;
        }

        $task = Task::where('id', $taskId)
            ->where('user_id', $user->id)
            ->with(['priority', 'project', 'tags'])
            ->first();

        if (!$task) {
            throw new TaskNotFoundException("Task {$taskId} not found");
        }

        switch ($action) {
            case 'complete':
                $taskService->toggleComplete($task);
                $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::SUCCESS . ' Задача выполнена!');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;

            case 'uncomplete':
                $taskService->toggleComplete($task);
                $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::BACK . ' Отметка снята');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;

            case 'details':
                $this->showTaskDetails($task, $chatId, $messageId, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'confirmdelete':
                $text = TelegramIcons::WARNING . " <b>Удалить задачу?</b>\n\n";
                $text .= TelegramIcons::TASK . " {$task->title}\n\n";
                $text .= "<i>Это действие нельзя отменить.</i>";
                $botService->editMessage($chatId, $messageId, $text, $this->keyboardService->getDeleteConfirmInline($task->id));
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'delete':
                $taskService->deleteTask($task);
                $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::TASK_DELETE . ' Задача удалена');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;

            case 'setpriority':
                $text = TelegramIcons::PRIORITY . " <b>Выберите приоритет</b>\n\n";
                $text .= TelegramIcons::TASK . " {$task->title}";
                $botService->editMessage($chatId, $messageId, $text, $this->keyboardService->getPrioritySelectInline($task->id, $task->priority_id));
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'setproject':
                $text = TelegramIcons::PROJECT . " <b>Выберите проект</b>\n\n";
                $text .= TelegramIcons::TASK . " {$task->title}";
                $botService->editMessage($chatId, $messageId, $text, $this->keyboardService->getProjectSelectInline($task->id, $user->id, $task->project_id));
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'setdate':
                $text = TelegramIcons::CALENDAR . " <b>Выберите срок</b>\n\n";
                $text .= TelegramIcons::TASK . " {$task->title}";
                $botService->editMessage($chatId, $messageId, $text, $this->keyboardService->getDateSelectInline($task->id));
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'edit':
                $botService->answerCallbackQuery($callbackQueryId, 'Редактирование доступно на сайте', true);
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // УСТАНОВКА СВОЙСТВ ЗАДАЧИ
    // ═══════════════════════════════════════════════════════════════

    protected function setTaskPriority(
        ?string $taskId,
        ?string $priorityId,
        int $chatId,
        int $messageId,
        string $callbackQueryId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        $task = Task::where('id', $taskId)->where('user_id', $user->id)->first();
        if (!$task) {
            throw new TaskNotFoundException("Task {$taskId} not found");
        }

        $task->priority_id = $priorityId == '0' ? null : $priorityId;
        $task->save();

        $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::SUCCESS . ' Приоритет изменён');

        // Возвращаемся к деталям задачи
        $task->load(['priority', 'project', 'tags']);
        $this->showTaskDetails($task, $chatId, $messageId, $botService, $telegramTaskService);
    }

    protected function setTaskProject(
        ?string $taskId,
        ?string $projectId,
        int $chatId,
        int $messageId,
        string $callbackQueryId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        $task = Task::where('id', $taskId)->where('user_id', $user->id)->first();
        if (!$task) {
            throw new TaskNotFoundException("Task {$taskId} not found");
        }

        $task->project_id = $projectId == '0' ? null : $projectId;
        $task->save();

        $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::SUCCESS . ' Проект изменён');

        // Возвращаемся к деталям задачи
        $task->load(['priority', 'project', 'tags']);
        $this->showTaskDetails($task, $chatId, $messageId, $botService, $telegramTaskService);
    }

    protected function setTaskDate(
        ?string $taskId,
        ?string $dateOption,
        int $chatId,
        int $messageId,
        string $callbackQueryId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
        $task = Task::where('id', $taskId)->where('user_id', $user->id)->first();
        if (!$task) {
            throw new TaskNotFoundException("Task {$taskId} not found");
        }

        switch ($dateOption) {
            case 'today':
                $task->due_date = today();
                break;
            case 'tomorrow':
                $task->due_date = today()->addDay();
                break;
            case 'week':
                $task->due_date = today()->addWeek();
                break;
            case 'none':
                $task->due_date = null;
                break;
        }
        $task->save();

        $botService->answerCallbackQuery($callbackQueryId, TelegramIcons::SUCCESS . ' Срок изменён');

        // Возвращаемся к деталям задачи
        $task->load(['priority', 'project', 'tags']);
        $this->showTaskDetails($task, $chatId, $messageId, $botService, $telegramTaskService);
    }

    // ═══════════════════════════════════════════════════════════════
    // ПРОЕКТЫ
    // ═══════════════════════════════════════════════════════════════

    protected function handleProjectCallback(
        string $action,
        ?string $projectId,
        int $chatId,
        int $messageId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        switch ($action) {
            case 'tasks':
                if ($projectId == '0') {
                    // Входящие (без проекта)
                    $tasks = Task::where('user_id', $user->id)
                        ->whereNull('project_id')
                        ->where('completed', false)
                        ->with(['priority', 'project', 'tags'])
                        ->orderBy('order')
                        ->get();
                    $title = TelegramIcons::INBOX . ' Входящие';
                } else {
                    $project = Project::find($projectId);
                    $tasks = Task::where('user_id', $user->id)
                        ->where('project_id', $projectId)
                        ->where('completed', false)
                        ->with(['priority', 'project', 'tags'])
                        ->orderBy('order')
                        ->get();
                    $title = TelegramIcons::PROJECT . " {$project->name}";
                }

                $formatted = $telegramTaskService->formatTasksList($tasks, $title);
                $keyboard = [
                    'inline_keyboard' => $formatted['keyboard'] ?? [],
                ];
                $keyboard['inline_keyboard'][] = [
                    ['text' => TelegramIcons::BACK . ' К проектам', 'callback_data' => 'menu_projects'],
                ];
                $botService->sendMessage($chatId, $formatted['text'], $keyboard);
                break;

            case 'new':
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::PROJECT_NEW . " <b>Создание проекта</b>\n\n" .
                    "Создание проектов доступно в веб-версии.\n\n" .
                    TelegramIcons::WEB . " " . config('app.url'),
                    [
                        'inline_keyboard' => [
                            [['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')]],
                            [['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => 'menu_projects']],
                        ],
                    ]
                );
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ФИЛЬТРЫ
    // ═══════════════════════════════════════════════════════════════

    protected function showFilteredTasks(
        string $filter,
        int $chatId,
        int $messageId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        $titles = [
            'active' => 'Все задачи',
            'today' => TelegramIcons::TODAY . ' Сегодня',
            'completed' => TelegramIcons::TASK_DONE . ' Выполненные',
            'overdue' => TelegramIcons::OVERDUE . ' Просроченные',
        ];

        $tasks = $telegramTaskService->getTasksList($user, $filter);
        $formatted = $telegramTaskService->formatTasksList($tasks, $titles[$filter] ?? 'Задачи');

        $keyboard = $this->keyboardService->getTasksListInline($filter);
        if ($formatted['keyboard']) {
            $keyboard['inline_keyboard'] = array_merge($formatted['keyboard'], $keyboard['inline_keyboard']);
        }

        try {
            $botService->editMessage($chatId, $messageId, $formatted['text'], $keyboard);
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                throw $e;
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // СПРАВКА
    // ═══════════════════════════════════════════════════════════════

    protected function handleHelpCallback(string $topic, int $chatId, TelegramBotService $botService): void
    {
        $helpCommand = new HelpCommand($botService);

        switch ($topic) {
            case 'tasks':
                $helpCommand->sendTasksHelp($chatId);
                break;
            case 'projects':
                $helpCommand->sendProjectsHelp($chatId);
                break;
            case 'link':
                $helpCommand->sendLinkHelp($chatId);
                break;
            default:
                $helpCommand->sendHelp($chatId);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // НАСТРОЙКИ
    // ═══════════════════════════════════════════════════════════════

    protected function handleSettingsCallback(
        string $action,
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramAuthService $authService
    ): void {
        switch ($action) {
            case 'notifications':
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::BELL . " <b>Уведомления</b>\n\n" .
                    "Настройка уведомлений доступна в веб-версии.",
                    [
                        'inline_keyboard' => [
                            [['text' => TelegramIcons::WEB . ' Открыть настройки', 'url' => config('app.url') . '/settings']],
                            [['text' => TelegramIcons::BACK . ' Назад', 'callback_data' => 'menu_settings']],
                        ],
                    ]
                );
                break;

            case 'unlink':
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::WARNING . " <b>Отвязать аккаунт?</b>\n\n" .
                    "Вы больше не сможете управлять задачами через Telegram.\n\n" .
                    "<i>Чтобы привязать снова, перейдите на сайт.</i>",
                    [
                        'inline_keyboard' => [
                            [['text' => TelegramIcons::ERROR . ' Да, отвязать', 'callback_data' => 'settings_confirmunlink']],
                            [['text' => TelegramIcons::BACK . ' Отмена', 'callback_data' => 'menu_settings']],
                        ],
                    ]
                );
                break;

            case 'confirmunlink':
                $authService->unlinkAccount($user->id);
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::SUCCESS . " <b>Аккаунт отвязан</b>\n\n" .
                    "Используйте /start для повторной привязки.",
                    $this->keyboardService->removeKeyboard()
                );
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // БЫСТРОЕ СОЗДАНИЕ
    // ═══════════════════════════════════════════════════════════════

    protected function handleQuickAdd(
        string $hash,
        int $chatId,
        $user,
        TelegramBotService $botService,
        TaskService $taskService
    ): void {
        $title = cache()->pull('quickadd_' . $hash);

        if (!$title) {
            $botService->sendMessage($chatId, TelegramIcons::ERROR . ' Время создания истекло. Попробуйте снова.');
            return;
        }

        $dto = TaskDTO::fromArray([
            'title' => $title,
            'user_id' => $user->id,
        ]);
        $task = $taskService->createTask($dto);

        $botService->sendMessage(
            $chatId,
            TelegramIcons::SUCCESS . " <b>Задача создана!</b>\n\n" .
            TelegramIcons::TASK . " {$task->title}",
            $this->keyboardService->getTaskDetailsInline($task)
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // НОВАЯ ЗАДАЧА
    // ═══════════════════════════════════════════════════════════════

    protected function handleNewTaskCallback(
        string $action,
        ?string $param,
        int $chatId,
        $user,
        TelegramBotService $botService
    ): void {
        switch ($action) {
            case 'cancel':
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::ERROR . " Создание отменено.",
                    $this->keyboardService->getQuickActionsInline()
                );
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // УТИЛИТЫ
    // ═══════════════════════════════════════════════════════════════

    protected function handleRefresh(
        string $type,
        int $chatId,
        int $messageId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        switch ($type) {
            case 'profile':
                $this->showProfile($chatId, $user, $botService);
                break;
            default:
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
        }
    }

    protected function handleBack(
        string $type,
        int $chatId,
        int $messageId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        switch ($type) {
            case 'tasks':
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;
            case 'projects':
                $botService->sendMessage($chatId, TelegramIcons::PROJECT . " <b>Проекты</b>", $this->keyboardService->getProjectsListInline($user->id));
                break;
        }
    }

    protected function refreshTasksList(
        int $chatId,
        int $messageId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        $tasks = $telegramTaskService->getTasksList($user, 'active');
        $formatted = $telegramTaskService->formatTasksList($tasks, 'Все задачи');

        $keyboard = $this->keyboardService->getTasksListInline('active');
        if ($formatted['keyboard']) {
            $keyboard['inline_keyboard'] = array_merge($formatted['keyboard'], $keyboard['inline_keyboard']);
        }

        try {
            $botService->editMessage($chatId, $messageId, $formatted['text'], $keyboard);
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                throw $e;
            }
        }
    }

    protected function showTaskDetails(
        Task $task,
        int $chatId,
        int $messageId,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        $message = $telegramTaskService->formatTaskMessage($task, true);

        try {
            $botService->editMessage(
                $chatId,
                $messageId,
                $message,
                $this->keyboardService->getTaskDetailsInline($task)
            );
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                throw $e;
            }
        }
    }

    protected function showProfile(int $chatId, $user, TelegramBotService $botService): void
    {
        $totalCompleted = Task::where('user_id', $user->id)->where('completed', true)->count();
        $activeTasks = Task::where('user_id', $user->id)->where('completed', false)->count();
        $overdueTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->whereDate('due_date', '<', today())
            ->count();
        $completedThisWeek = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $message = TelegramIcons::STATS . " <b>Ваша статистика</b>\n\n";
        $message .= TelegramIcons::USER . " Имя: <b>{$user->name}</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= TelegramIcons::TASK_LIST . " Активных: <b>{$activeTasks}</b>\n";
        $message .= TelegramIcons::TASK_DONE . " Выполнено: <b>{$totalCompleted}</b>\n";
        $message .= TelegramIcons::OVERDUE . " Просрочено: <b>{$overdueTasks}</b>\n\n";
        $message .= TelegramIcons::CHART_UP . " За неделю: <b>{$completedThisWeek}</b>";

        $botService->sendMessage($chatId, $message, $this->keyboardService->getProfileInline());
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Failed to process callback query', [
            'callback_id' => $this->callbackData['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
