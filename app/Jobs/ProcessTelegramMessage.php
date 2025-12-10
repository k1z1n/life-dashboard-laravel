<?php

namespace App\Jobs;

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

            // Обработка кнопок Reply Keyboard (главное меню)
            $handled = $this->handleReplyKeyboard(
                $text,
                $chatId,
                $user,
                $botService,
                $telegramTaskService,
                $keyboardService
            );

            if (!$handled) {
                // Неизвестное сообщение — предлагаем создать задачу или показать помощь
                $this->handleUnknownText($chatId, $text, $botService, $keyboardService);
            }

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
        TelegramKeyboardService $keyboardService
    ): bool {
        // Убираем emoji из начала для сравнения
        $cleanText = $this->cleanButtonText($text);

        switch ($cleanText) {
            case 'Мои задачи':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'active');
                return true;

            case 'Сегодня':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'today');
                return true;

            case 'Создать задачу':
                $this->startCreateTask($chatId, $botService);
                return true;

            case 'Просрочено':
                $this->showTasks($chatId, $user, $botService, $telegramTaskService, $keyboardService, 'overdue');
                return true;

            case 'Проекты':
                $this->showProjects($chatId, $user, $botService, $keyboardService);
                return true;

            case 'Статистика':
                $this->showProfile($chatId, $user, $botService, $keyboardService);
                return true;

            case 'Настройки':
                $this->showSettings($chatId, $botService, $keyboardService);
                return true;

            case 'Помощь':
                $helpCommand = new HelpCommand($botService);
                $helpCommand->sendHelp($chatId);
                return true;

            default:
                return false;
        }
    }

    /**
     * Убрать emoji из текста кнопки
     */
    protected function cleanButtonText(string $text): string
    {
        // Убираем все emoji и лишние пробелы
        $clean = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $text); // emoticons
        $clean = preg_replace('/[\x{1F300}-\x{1F5FF}]/u', '', $clean); // symbols
        $clean = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $clean); // transport
        $clean = preg_replace('/[\x{1F1E0}-\x{1F1FF}]/u', '', $clean); // flags
        $clean = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $clean); // misc symbols
        $clean = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $clean); // dingbats
        $clean = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $clean); // variation selectors
        $clean = preg_replace('/[\x{1F900}-\x{1F9FF}]/u', '', $clean); // supplemental symbols
        $clean = preg_replace('/[\x{1FA00}-\x{1FA6F}]/u', '', $clean); // chess symbols
        $clean = preg_replace('/[\x{1FA70}-\x{1FAFF}]/u', '', $clean); // symbols extended
        $clean = preg_replace('/[\x{231A}-\x{231B}]/u', '', $clean); // watch, hourglass
        $clean = preg_replace('/[\x{23E9}-\x{23F3}]/u', '', $clean); // media symbols
        $clean = preg_replace('/[\x{23F8}-\x{23FA}]/u', '', $clean); // media symbols 2
        $clean = preg_replace('/[\x{25AA}-\x{25AB}]/u', '', $clean); // squares
        $clean = preg_replace('/[\x{25B6}]/u', '', $clean); // play button
        $clean = preg_replace('/[\x{25C0}]/u', '', $clean); // reverse button
        $clean = preg_replace('/[\x{25FB}-\x{25FE}]/u', '', $clean); // squares 2
        $clean = preg_replace('/[\x{2614}-\x{2615}]/u', '', $clean); // umbrella, coffee
        $clean = preg_replace('/[\x{2648}-\x{2653}]/u', '', $clean); // zodiac
        $clean = preg_replace('/[\x{267F}]/u', '', $clean); // wheelchair
        $clean = preg_replace('/[\x{2693}]/u', '', $clean); // anchor
        $clean = preg_replace('/[\x{26A1}]/u', '', $clean); // high voltage
        $clean = preg_replace('/[\x{26AA}-\x{26AB}]/u', '', $clean); // circles
        $clean = preg_replace('/[\x{26BD}-\x{26BE}]/u', '', $clean); // balls
        $clean = preg_replace('/[\x{26C4}-\x{26C5}]/u', '', $clean); // weather
        $clean = preg_replace('/[\x{26CE}]/u', '', $clean); // ophiuchus
        $clean = preg_replace('/[\x{26D4}]/u', '', $clean); // no entry
        $clean = preg_replace('/[\x{26EA}]/u', '', $clean); // church
        $clean = preg_replace('/[\x{26F2}-\x{26F3}]/u', '', $clean); // fountain, golf
        $clean = preg_replace('/[\x{26F5}]/u', '', $clean); // sailboat
        $clean = preg_replace('/[\x{26FA}]/u', '', $clean); // tent
        $clean = preg_replace('/[\x{26FD}]/u', '', $clean); // fuel pump
        $clean = preg_replace('/[\x{2702}]/u', '', $clean); // scissors
        $clean = preg_replace('/[\x{2705}]/u', '', $clean); // check mark
        $clean = preg_replace('/[\x{2708}-\x{270D}]/u', '', $clean); // airplane to writing hand
        $clean = preg_replace('/[\x{270F}]/u', '', $clean); // pencil
        $clean = preg_replace('/[\x{2712}]/u', '', $clean); // black nib
        $clean = preg_replace('/[\x{2714}]/u', '', $clean); // check mark
        $clean = preg_replace('/[\x{2716}]/u', '', $clean); // x mark
        $clean = preg_replace('/[\x{271D}]/u', '', $clean); // cross
        $clean = preg_replace('/[\x{2721}]/u', '', $clean); // star of david
        $clean = preg_replace('/[\x{2728}]/u', '', $clean); // sparkles
        $clean = preg_replace('/[\x{2733}-\x{2734}]/u', '', $clean); // eight spoked asterisk
        $clean = preg_replace('/[\x{2744}]/u', '', $clean); // snowflake
        $clean = preg_replace('/[\x{2747}]/u', '', $clean); // sparkle
        $clean = preg_replace('/[\x{274C}]/u', '', $clean); // cross mark
        $clean = preg_replace('/[\x{274E}]/u', '', $clean); // cross mark button
        $clean = preg_replace('/[\x{2753}-\x{2755}]/u', '', $clean); // question marks
        $clean = preg_replace('/[\x{2757}]/u', '', $clean); // exclamation mark
        $clean = preg_replace('/[\x{2763}-\x{2764}]/u', '', $clean); // heart exclamation
        $clean = preg_replace('/[\x{2795}-\x{2797}]/u', '', $clean); // plus, minus, divide
        $clean = preg_replace('/[\x{27A1}]/u', '', $clean); // right arrow
        $clean = preg_replace('/[\x{27B0}]/u', '', $clean); // curly loop
        $clean = preg_replace('/[\x{27BF}]/u', '', $clean); // double curly loop
        $clean = preg_replace('/[\x{2934}-\x{2935}]/u', '', $clean); // arrows
        $clean = preg_replace('/[\x{2B05}-\x{2B07}]/u', '', $clean); // arrows
        $clean = preg_replace('/[\x{2B1B}-\x{2B1C}]/u', '', $clean); // squares
        $clean = preg_replace('/[\x{2B50}]/u', '', $clean); // star
        $clean = preg_replace('/[\x{2B55}]/u', '', $clean); // circle
        $clean = preg_replace('/[\x{3030}]/u', '', $clean); // wavy dash
        $clean = preg_replace('/[\x{303D}]/u', '', $clean); // part alternation mark
        $clean = preg_replace('/[\x{3297}]/u', '', $clean); // circled ideograph congratulation
        $clean = preg_replace('/[\x{3299}]/u', '', $clean); // circled ideograph secret
        $clean = preg_replace('/[\x{FE0F}]/u', '', $clean); // variation selector
        $clean = preg_replace('/[\x{200D}]/u', '', $clean); // zero width joiner

        return trim($clean);
    }

    /**
     * Показать список задач
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
            'today' => 'Задачи на сегодня',
            'completed' => 'Выполненные',
            'overdue' => 'Просроченные',
        ];

        $tasks = $telegramTaskService->getTasksList($user, $filter);
        $formatted = $telegramTaskService->formatTasksList($tasks, $titles[$filter] ?? 'Задачи');

        // Добавляем фильтры
        $keyboard = $keyboardService->getTasksListInline($filter);

        // Добавляем кнопки задач
        if ($formatted['keyboard']) {
            $keyboard['inline_keyboard'] = array_merge(
                $formatted['keyboard'],
                $keyboard['inline_keyboard']
            );
        }

        $botService->sendMessage($chatId, $formatted['text'], $keyboard);
    }

    /**
     * Начать создание задачи
     */
    protected function startCreateTask(int $chatId, TelegramBotService $botService): void
    {
        $text = TelegramIcons::TASK_NEW . " <b>Создание задачи</b>\n\n";
        $text .= "Введите название новой задачи:\n\n";
        $text .= TelegramIcons::BULB . " <i>Или используйте быструю команду:</i>\n";
        $text .= "<code>/add Название задачи</code>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => TelegramIcons::ERROR . ' Отмена', 'callback_data' => 'newtask_cancel']],
            ],
        ];

        $botService->sendMessage($chatId, $text, $keyboard);

        // TODO: Активировать conversation state для ввода названия
    }

    /**
     * Показать проекты
     */
    protected function showProjects(
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $text = TelegramIcons::PROJECT . " <b>Ваши проекты</b>\n\n";
        $text .= "Выберите проект для просмотра задач:";

        $keyboard = $keyboardService->getProjectsListInline($user->id);

        $botService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Показать статистику профиля
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

        // Добавим мотивационное сообщение
        if ($completedThisWeek >= 10) {
            $message .= "\n" . TelegramIcons::FIRE . " <b>Отличный темп!</b>";
        } elseif ($activeTasks == 0) {
            $message .= "\n" . TelegramIcons::PARTY . " <b>Все задачи выполнены!</b>";
        } elseif ($overdueTasks > 0) {
            $message .= "\n" . TelegramIcons::WARNING . " <i>Есть просроченные задачи</i>";
        }

        $botService->sendMessage($chatId, $message, $keyboardService->getProfileInline());
    }

    /**
     * Показать настройки
     */
    protected function showSettings(
        int $chatId,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $text = TelegramIcons::SETTINGS . " <b>Настройки</b>\n\n";
        $text .= "Управление вашим Telegram-аккаунтом:";

        $botService->sendMessage($chatId, $text, $keyboardService->getSettingsInline());
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
        $state = $conversationManager->getState($chatId);

        switch ($state['action'] ?? null) {
            case 'create_task':
                // Пользователь вводит название задачи
                $task = $taskService->createTask([
                    'title' => $text,
                    'user_id' => $user->id,
                ]);

                $conversationManager->clearState($chatId);

                $botService->sendMessage(
                    $chatId,
                    TelegramIcons::SUCCESS . " <b>Задача создана!</b>\n\n" .
                    TelegramIcons::TASK . " {$task->title}",
                    $keyboardService->getTaskDetailsInline($task)
                );
                break;

            default:
                $conversationManager->clearState($chatId);
                $botService->sendMessage(
                    $chatId,
                    "Операция отменена. Используйте кнопки меню для навигации."
                );
        }
    }

    /**
     * Обработка неизвестного текста
     */
    protected function handleUnknownText(
        int $chatId,
        string $text,
        TelegramBotService $botService,
        TelegramKeyboardService $keyboardService
    ): void {
        $message = TelegramIcons::BULB . " Не понял вашу команду.\n\n";
        $message .= "Используйте <b>кнопки меню</b> внизу экрана\n";
        $message .= "или одну из команд:\n\n";
        $message .= "<code>/add {$text}</code> — создать задачу\n";
        $message .= "<code>/help</code> — справка";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::TASK_NEW . " Создать задачу «{$text}»", 'callback_data' => 'quickadd_' . substr(md5($text), 0, 8)],
                ],
                [
                    ['text' => TelegramIcons::HELP . ' Помощь', 'callback_data' => 'menu_help'],
                ],
            ],
        ];

        // Сохраним текст для быстрого создания
        cache()->put('quickadd_' . substr(md5($text), 0, 8), $text, now()->addMinutes(5));

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

