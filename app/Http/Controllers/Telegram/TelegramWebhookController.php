<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\Telegram\CommandHandler;
use App\Services\Telegram\ConversationManager;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramTaskService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Telegram\Bot\Api;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService,
        protected TelegramTaskService $telegramTaskService,
        protected TaskService $taskService,
        protected CommandHandler $commandHandler,
        protected ConversationManager $conversationManager
    ) {}

    public function webhook(Request $request)
    {
        try {
            $telegram = new Api(config('telegram.bot_token'));
            $update = $telegram->getWebhookUpdate();

            \Log::channel('telegram')->info('Telegram webhook received', [
                'update_id' => $update->get('update_id'),
            ]);

            // Dispatch to queue for async processing
            \App\Jobs\ProcessTelegramUpdate::dispatch($update->toArray())
                ->onQueue('telegram');

            // Return immediate response to Telegram
            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return 200 to Telegram to avoid retries
            return response()->json(['ok' => true]);
        }
    }

    protected function handleCallbackQuery($callbackQuery): void
    {
        $chatId = $callbackQuery->getMessage()->getChat()->id;
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $data = $callbackQuery->getData();
        $telegramId = $callbackQuery->getFrom()->id;

        \Log::info('Callback query received', ['data' => $data]);

        // Check if user is linked
        $user = $this->authService->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->botService->answerCallbackQuery(
                $callbackQuery->getId(),
                'Аккаунт не привязан. Используйте /start',
                true
            );
            return;
        }

        // Parse callback data
        $parts = explode('_', $data);
        $action = $parts[0];
        $type = $parts[1] ?? null;
        $id = $parts[2] ?? null;

        try {
            switch ($action) {
                case 'task':
                    $this->handleTaskAction($type, $id, $chatId, $messageId, $callbackQuery->getId(), $user);
                    break;

                case 'cmd':
                    $this->handleCommandCallback($type, $chatId, $user);
                    break;

                case 'refresh':
                    $this->handleRefresh($type, $chatId, $messageId, $user);
                    break;

                case 'back':
                    $this->handleBack($type, $chatId, $messageId, $user);
                    break;

                default:
                    $this->botService->answerCallbackQuery($callbackQuery->getId(), 'Неизвестная команда');
            }
        } catch (\Exception $e) {
            \Log::error('Callback query error', ['error' => $e->getMessage()]);
            $this->botService->answerCallbackQuery(
                $callbackQuery->getId(),
                'Произошла ошибка: ' . $e->getMessage(),
                true
            );
        }
    }

    protected function handleTaskAction(string $action, ?string $taskId, int $chatId, int $messageId, string $callbackQueryId, $user): void
    {
        if (!$taskId) {
            $this->botService->answerCallbackQuery($callbackQueryId, 'ID задачи не указан', true);
            return;
        }

        $task = Task::where('id', $taskId)
            ->where('user_id', $user->id)
            ->with(['priority', 'project', 'tags'])
            ->first();

        if (!$task) {
            $this->botService->answerCallbackQuery($callbackQueryId, 'Задача не найдена', true);
            return;
        }

        switch ($action) {
            case 'complete':
                $this->taskService->toggleComplete($task);
                $this->botService->answerCallbackQuery($callbackQueryId, '✅ Задача выполнена!');
                $this->refreshTasksList($chatId, $messageId, $user);
                break;

            case 'uncomplete':
                $this->taskService->toggleComplete($task);
                $this->botService->answerCallbackQuery($callbackQueryId, '↩️ Отметка снята');
                $this->refreshTasksList($chatId, $messageId, $user);
                break;

            case 'details':
                $this->showTaskDetails($task, $chatId, $messageId);
                $this->botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'delete':
                $this->taskService->deleteTask($task);
                $this->botService->answerCallbackQuery($callbackQueryId, '🗑️ Задача удалена');
                $this->refreshTasksList($chatId, $messageId, $user);
                break;
        }
    }

    protected function handleCommandCallback(string $command, int $chatId, $user): void
    {
        switch ($command) {
            case 'tasks':
                $tasks = $this->telegramTaskService->getTasksList($user, 'active');
                $formatted = $this->telegramTaskService->formatTasksList($tasks, 'Все задачи');
                $this->botService->sendMessage(
                    $chatId,
                    $formatted['text'],
                    $formatted['keyboard'] ? $this->botService->createInlineKeyboard($formatted['keyboard']) : null
                );
                break;

            case 'profile':
                $this->showProfile($chatId, $user);
                break;

            case 'help':
                $command = $this->commandHandler->getCommand('help');
                if ($command) {
                    $mockMessage = new \Telegram\Bot\Objects\Message(['chat' => ['id' => $chatId]]);
                    $mockMessage->chat = new \stdClass();
                    $mockMessage->chat->id = $chatId;
                    $command->execute($mockMessage);
                }
                break;
        }
    }

    protected function handleRefresh(string $type, int $chatId, int $messageId, $user): void
    {
        $this->refreshTasksList($chatId, $messageId, $user);
    }

    protected function handleBack(string $type, int $chatId, int $messageId, $user): void
    {
        if ($type === 'tasks') {
            $this->refreshTasksList($chatId, $messageId, $user);
        }
    }

    protected function refreshTasksList(int $chatId, int $messageId, $user): void
    {
        $tasks = $this->telegramTaskService->getTasksList($user, 'active');
        $formatted = $this->telegramTaskService->formatTasksList($tasks, 'Все задачи');

        $this->botService->editMessage(
            $chatId,
            $messageId,
            $formatted['text'],
            $formatted['keyboard'] ? $this->botService->createInlineKeyboard($formatted['keyboard']) : null
        );
    }

    protected function showTaskDetails(Task $task, int $chatId, int $messageId): void
    {
        $message = $this->telegramTaskService->formatTaskMessage($task, true);
        $keyboard = $this->telegramTaskService->getTaskActionsKeyboard($task);

        $this->botService->editMessage(
            $chatId,
            $messageId,
            $message,
            $this->botService->createInlineKeyboard($keyboard)
        );
    }

    protected function showProfile(int $chatId, $user): void
    {
        $totalCompleted = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $activeTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->count();

        $completedThisWeek = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $completedThisMonth = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->count();

        $overdueTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->whereDate('due_date', '<', today())
            ->count();

        $message = "📊 <b>Ваша статистика</b>\n\n";
        $message .= "👤 Имя: <b>{$user->name}</b>\n\n";
        $message .= "✅ Всего выполнено: <b>{$totalCompleted}</b>\n";
        $message .= "📋 Активных задач: <b>{$activeTasks}</b>\n";
        $message .= "⚠️ Просроченных: <b>{$overdueTasks}</b>\n\n";
        $message .= "📈 За эту неделю: <b>{$completedThisWeek}</b>\n";
        $message .= "📈 За этот месяц: <b>{$completedThisMonth}</b>\n";

        $keyboard = [
            [
                ['text' => '📋 Все задачи', 'callback_data' => 'cmd_tasks'],
            ],
            [
                ['text' => '🌐 Открыть веб-версию', 'url' => config('app.url')],
            ],
        ];

        $this->botService->sendMessage(
            $chatId,
            $message,
            $this->botService->createInlineKeyboard($keyboard)
        );
    }

    protected function handleTextMessage(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $telegramId = $message->getFrom()->id;
        $text = $message->getText();

        // Check if user is linked
        $user = $this->authService->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Аккаунт не привязан.\n\nИспользуйте /start для привязки."
            );
            return;
        }

        // Check if user is in conversation
        if ($this->conversationManager->hasState($chatId)) {
            $this->handleConversation($chatId, $text, $user);
            return;
        }

        // Default response
        $this->botService->sendMessage(
            $chatId,
            "Используйте команды для управления задачами.\n\n" .
            "Например:\n" .
            "/add Название задачи - создать задачу\n" .
            "/tasks - показать все задачи\n" .
            "/help - список всех команд"
        );
    }

    protected function handleConversation(int $chatId, string $text, $user): void
    {
        // TODO: Implement conversation handling for multi-step operations
        // This will be used for /new command and interactive task creation
        $this->conversationManager->clearState($chatId);
        $this->botService->sendMessage(
            $chatId,
            "Операция отменена. Используйте /help для списка команд."
        );
    }
}
