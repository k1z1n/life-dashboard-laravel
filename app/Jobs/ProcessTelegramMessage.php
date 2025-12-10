<?php

namespace App\Jobs;

use App\DTOs\TaskDTO;
use App\Models\Task;
use App\Services\Telegram\ConversationManager;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramKeyboardService;
use App\Services\Telegram\TelegramTaskService;
use App\Services\Telegram\TelegramIcons;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\TaskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Обработка текстовых сообщений (Reply Keyboard и свободный текст)
 * Контекстные клавиатуры меняются в зависимости от действия
 */
class ProcessTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [5, 15];
    public $timeout = 60;

    public function __construct(
        protected array $messageData
    ) {}

    public function handle(
        TelegramBotService $botService,
        TelegramAuthService $authService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService,
        ConversationManager $conversationManager
    ): void {
        try {
            $chatId = $this->messageData['chat']['id'];
            $telegramId = $this->messageData['from']['id'];
            $text = $this->messageData['text'] ?? '';

            Log::channel('telegram')->info('Processing text message', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            // Проверка авторизации
            $user = $authService->getUserByTelegramId($telegramId);
            if (!$user) {
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::ERROR . " Аккаунт не привязан.\n\nИспользуйте /start для привязки."
                );
                return;
            }

            $keyboardService = new TelegramKeyboardService();

            // Проверяем, есть ли активный диалог (conversation)
            if ($conversationManager->hasState($chatId)) {
                $this->handleConversation($chatId, $text, $user, $botService, $taskService, $conversationManager, $keyboardService);
                return;
            }

            // Обработка кнопок Reply Keyboard
            $this->handleReplyKeyboard(
                $text,
                $chatId,
                $user,
                $botService,
                $telegramTaskService,
                $taskService,
                $conversationManager,
                $keyboardService
            );

        } catch (\Exception $e) {
            Log::channel('telegram')->error('Error processing text message', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Обработка нажатий Reply Keyboard (текстовые кнопки)
     */
    protected function handleReplyKeyboard(
        string $text,
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService,
        ConversationManager $conversationManager,
        TelegramKeyboardService $keyboardService
    ): void {
        // Убираем emoji из начала для сравнения
        $cleanText = $this->cleanButtonText($text);

        switch ($cleanText) {
            // ═══════════════════════════════════════
            // ГЛАВНОЕ МЕНЮ
            // ═══════════════════════════════════════
            case 'Мои задачи':
            case 'Все':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'active');
                break;

            case 'Сегодня':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'today');
                break;

            case 'Готово':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'completed');
                break;

            case 'Просрочено':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'overdue');
                break;

            case 'Создать':
            case 'Создать задачу':
                $this->startCreateTask($chatId, $botService, $conversationManager, $keyboardService);
                break;

            case 'Проекты':
                $this->showProjects($chatId, $user, $botService, $keyboardService);
                break;

            case 'Статистика':
                $this->showProfile($chatId, $user, $botService, $keyboardService);
                break;

            case 'Настройки':
                $this->showSettings($chatId, $botService, $keyboardService);
                break;

            case 'Помощь':
                $this->showHelp($chatId, $botService, $keyboardService);
                break;

            // ═══════════════════════════════════════
            // НАВИГАЦИЯ
            // ═══════════════════════════════════════
            case 'Главное меню':
            case 'Меню':
                $this->showMainMenu($chatId, $user, $botService, $keyboardService);
                break;

            case 'Назад':
            case 'К списку':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'active');
                break;

            case 'Обновить':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'active');
                break;

            // ═══════════════════════════════════════
            // ОТМЕНА / ПОДТВЕРЖДЕНИЕ
            // ═══════════════════════════════════════
            case 'Отмена':
                $conversationManager->clearState($chatId);
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::ERROR . " Действие отменено.",
                    $keyboardService->getMainMenuKeyboard()
                );
                break;

            case 'Да':
                // Обработка подтверждения через conversation state
                $this->handleConfirmation($chatId, true, $user, $botService, $taskService, $conversationManager, $keyboardService);
                break;

            case 'Нет':
                $conversationManager->clearState($chatId);
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::SUCCESS . " Отменено.",
                    $keyboardService->getMainMenuKeyboard()
                );
                break;

            // ═══════════════════════════════════════
            // НЕИЗВЕСТНЫЙ ТЕКСТ
            // ═══════════════════════════════════════
            default:
                $this->handleUnknownText($chatId, $text, $user, $botService, $taskService, $conversationManager, $keyboardService);
        }
    }

    /**
     * Убрать emoji из текста кнопки
     */
    protected function cleanButtonText(string $text): string
    {
        // Удаляем все emoji и спецсимволы
        $clean = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
        $clean = preg_replace('/[\x{2000}-\x{2BFF}]/u', '', $clean);
        $clean = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $clean);
        $clean = preg_replace('/[\x{200D}]/u', '', $clean);

        return trim($clean);
    }

    /**
     * 🏠 Показать главное меню
     */
    protected function showMainMenu(
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $message = TelegramIcons::HOME . " <b>Главное меню</b>\n\n";
        $message .= "Привет, <b>{$user->name}</b>! " . TelegramIcons::WAVE . "\n\n";
        $message .= "Выберите действие:";

        $botService->sendMessage($chatId, $message, $keyboardService->getMainMenuKeyboard());
    }

    /**
     * 📋 Показать список задач
     */
    protected function showTasks(
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService,
        TelegramKeyboardService $keyboardService,
        string $filter
    ): void {
        $titles = [
            'active' => 'Все задачи',
            'today' => TelegramIcons::TODAY . ' Сегодня',
            'completed' => TelegramIcons::TASK_DONE . ' Выполненные',
            'overdue' => TelegramIcons::OVERDUE . ' Просроченные',
        ];

        $tasks = $telegramTaskService->getTasksList($user, $filter);
            $formatted = $telegramTaskService->formatTasksList($tasks, $titles[$filter] ?? 'Задачи', 1, 5, $filter);

        // Собираем inline клавиатуру: сначала кнопки задач, потом фильтры
        $inlineKeyboard = ['inline_keyboard' => []];

        // Добавляем кнопки задач (если есть)
        if ($formatted['keyboard']) {
            $inlineKeyboard['inline_keyboard'] = $formatted['keyboard'];
        }

        // Добавляем фильтры внизу
        $filters = $keyboardService->getTasksFiltersInline($filter);
        $inlineKeyboard['inline_keyboard'] = array_merge(
            $inlineKeyboard['inline_keyboard'],
            $filters['inline_keyboard']
        );

        // Отправляем ОДНО сообщение со списком и кнопками
        $botService->sendMessage($chatId, $formatted['text'], $inlineKeyboard);
    }

    /**
     * ➕ Начать создание задачи
     */
    protected function startCreateTask(
        int $chatId,
        TelegramBotService $botService,
        ConversationManager $conversationManager,
        TelegramKeyboardService $keyboardService
    ): void {
        // Устанавливаем состояние диалога
        $conversationManager->setState($chatId, 'create_task', ['step' => 'title']);

        $text = TelegramIcons::TASK_NEW . " <b>Создание задачи</b>\n\n";
        $text .= "Введите название новой задачи:\n\n";
        $text .= TelegramIcons::BULB . " <i>Или нажмите «Отмена» для возврата</i>";

        // Показываем клавиатуру создания (с кнопкой Отмена)
        $botService->sendMessage($chatId, $text, $keyboardService->getCreateTaskKeyboard());
    }

    /**
     * 📁 Показать проекты
     */
    protected function showProjects(
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $text = TelegramIcons::PROJECT . " <b>Ваши проекты</b>\n\n";
        $text .= "Выберите проект для просмотра задач:";

        // Одно сообщение с inline кнопками проектов
        $botService->sendMessage($chatId, $text, $keyboardService->getProjectsListInline($user->id));
    }

    /**
     * 📊 Показать статистику профиля
     */
    protected function showProfile(
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
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
        $completedThisMonth = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->count();

        $message = TelegramIcons::STATS . " <b>Ваша статистика</b>\n\n";
        $message .= TelegramIcons::USER . " Имя: <b>{$user->name}</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= TelegramIcons::TASK_LIST . " Активных задач: <b>{$activeTasks}</b>\n";
        $message .= TelegramIcons::TASK_DONE . " Всего выполнено: <b>{$totalCompleted}</b>\n";
        $message .= TelegramIcons::OVERDUE . " Просроченных: <b>{$overdueTasks}</b>\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= TelegramIcons::CHART_UP . " <b>Прогресс:</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= TelegramIcons::CALENDAR . " За неделю: <b>{$completedThisWeek}</b>\n";
        $message .= TelegramIcons::CALENDAR . " За месяц: <b>{$completedThisMonth}</b>\n";

        if ($completedThisWeek >= 10) {
            $message .= "\n" . TelegramIcons::FIRE . " <b>Отличный темп!</b>";
        } elseif ($activeTasks == 0) {
            $message .= "\n" . TelegramIcons::PARTY . " <b>Все задачи выполнены!</b>";
        } elseif ($overdueTasks > 0) {
            $message .= "\n" . TelegramIcons::WARNING . " <i>Есть просроченные задачи</i>";
        }

        // Одно сообщение с inline кнопками
        $botService->sendMessage($chatId, $message, $keyboardService->getProfileInline());
    }

    /**
     * ⚙️ Показать настройки
     */
    protected function showSettings(
        int $chatId,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $text = TelegramIcons::SETTINGS . " <b>Настройки</b>\n\n";
        $text .= "Управление вашим аккаунтом:";

        // Одно сообщение с inline кнопками
        $botService->sendMessage($chatId, $text, $keyboardService->getSettingsInline());
    }

    /**
     * ❓ Показать справку
     */
    protected function showHelp(
        int $chatId,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $helpCommand = new HelpCommand($botService);
        $helpCommand->sendHelp($chatId);
    }

    /**
     * Обработка диалогов (conversations)
     */
    protected function handleConversation(
        int $chatId,
        string $text,
        $user,
        TelegramBotService $botService,
        TaskService $taskService,
        ConversationManager $conversationManager,
        TelegramKeyboardService $keyboardService
    ): void {
        $currentState = $conversationManager->getCurrentState($chatId);
        $data = $conversationManager->getData($chatId);
        $step = $data['step'] ?? null;

        // Проверяем, не нажал ли пользователь "Отмена"
        if ($this->cleanButtonText($text) === 'Отмена') {
            $conversationManager->clearState($chatId);
            $botService->sendMessage(
                $chatId,
                TelegramIcons::ERROR . " Действие отменено.",
                $keyboardService->getMainMenuKeyboard()
            );
            return;
        }

        switch ($currentState) {
            case 'create_task':
                if ($step === 'title') {
                    // Пользователь ввёл название задачи
                    $dto = TaskDTO::fromArray([
                        'title' => $text,
                        'user_id' => $user->id,
                    ]);
                    $task = $taskService->createTask($dto);

                    $conversationManager->clearState($chatId);

                    $botService->sendMessage(
                        $chatId,
                        TelegramIcons::SUCCESS . " <b>Задача создана!</b>\n\n" .
                        TelegramIcons::TASK . " {$task->title}",
                        $keyboardService->getMainMenuKeyboard()
                    );

                    // Inline кнопки для новой задачи
                    $botService->sendMessage(
                        $chatId,
                        TelegramIcons::TARGET . " <b>Настроить задачу:</b>",
                        $keyboardService->getTaskDetailsInline($task)
                    );
                }
                break;

            case 'delete_task':
                // Подтверждение удаления обрабатывается через кнопки Да/Нет
                break;

            default:
                $conversationManager->clearState($chatId);
                $botService->sendMessage(
                    $chatId,
                    "Операция отменена.",
                    $keyboardService->getMainMenuKeyboard()
                );
        }
    }

    /**
     * Обработка подтверждения (Да/Нет)
     */
    protected function handleConfirmation(
        int $chatId,
        bool $confirmed,
        $user,
        TelegramBotService $botService,
        TaskService $taskService,
        ConversationManager $conversationManager,
        TelegramKeyboardService $keyboardService
    ): void {
        $currentState = $conversationManager->getCurrentState($chatId);
        $data = $conversationManager->getData($chatId);

        if (!$currentState) {
            $botService->sendMessage(
                $chatId,
                "Нет активного действия для подтверждения.",
                $keyboardService->getMainMenuKeyboard()
            );
            return;
        }

        $conversationManager->clearState($chatId);

        if ($confirmed && $currentState === 'delete_task' && isset($data['task_id'])) {
            $task = Task::where('id', $data['task_id'])->where('user_id', $user->id)->first();
            if ($task) {
                $taskService->deleteTask($task);
                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::TASK_DELETE . " Задача удалена.",
                    $keyboardService->getMainMenuKeyboard()
                );
            }
        } else {
            $botService->sendMessage(
                $chatId,
                TelegramIcons::SUCCESS . " Готово.",
                $keyboardService->getMainMenuKeyboard()
            );
        }
    }

    /**
     * Обработка неизвестного текста
     */
    protected function handleUnknownText(
        int $chatId,
        string $text,
        $user,
        TelegramBotService $botService,
        TaskService $taskService,
        ConversationManager $conversationManager,
        TelegramKeyboardService $keyboardService
    ): void {
        // Предлагаем создать задачу с этим текстом
        $message = TelegramIcons::BULB . " Не понял команду.\n\n";
        $message .= "Хотите создать задачу с названием:\n";
        $message .= "<b>«{$text}»</b>?";

        // Сохраняем текст для быстрого создания
        $hash = substr(md5($text . time()), 0, 8);
        cache()->put('quickadd_' . $hash, $text, now()->addMinutes(5));

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_NEW . ' Да, создать задачу', 'callback_data' => 'quickadd_' . $hash],
                ],
                [
                    ['text' => TelegramIcons::ERROR . ' Нет', 'callback_data' => 'menu_main'],
                ],
            ],
        ];

        $botService->sendMessage($chatId, $message, $keyboard);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Failed to process text message', [
            'chat_id' => $this->messageData['chat']['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
