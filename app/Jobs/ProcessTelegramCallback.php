<?php

namespace App\Jobs;

use App\Exceptions\Telegram\TaskNotFoundException;
use App\Exceptions\Telegram\UnauthorizedException;
use App\Models\Task;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramTaskService;
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

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $callbackData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TelegramBotService $botService,
        TelegramAuthService $authService,
        TelegramTaskService $telegramTaskService,
        TaskService $taskService
    ): void {
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

            // Check if user is linked
            $user = $authService->getUserByTelegramId($telegramId);
            if (!$user) {
                throw new UnauthorizedException('Account not linked');
            }

            // Parse callback data
            $parts = explode('_', $data);
            $action = $parts[0];
            $type = $parts[1] ?? null;
            $id = $parts[2] ?? null;

            switch ($action) {
                case 'task':
                    $this->handleTaskAction(
                        $type,
                        $id,
                        $chatId,
                        $messageId,
                        $callbackQueryId,
                        $user,
                        $botService,
                        $telegramTaskService,
                        $taskService
                    );
                    break;

                case 'cmd':
                    $this->handleCommandCallback($type, $chatId, $user, $botService, $telegramTaskService);
                    $botService->answerCallbackQuery($callbackQueryId);
                    break;

                case 'refresh':
                    $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                    $botService->answerCallbackQuery($callbackQueryId, 'Обновлено');
                    break;

                case 'back':
                    $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                    $botService->answerCallbackQuery($callbackQueryId);
                    break;

                default:
                    $botService->answerCallbackQuery($callbackQueryId, 'Неизвестная команда');
            }

        } catch (UnauthorizedException $e) {
            Log::channel('telegram')->warning('Unauthorized callback query', [
                'telegram_id' => $this->callbackData['from']['id'] ?? null,
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Аккаунт не привязан', true);

        } catch (TaskNotFoundException $e) {
            Log::channel('telegram')->warning('Task not found in callback', [
                'error' => $e->getMessage(),
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Задача не найдена', true);

        } catch (\Exception $e) {
            Log::channel('telegram')->error('Error processing callback query', [
                'error' => $e->getMessage(),
            ]);
            $botService->answerCallbackQuery($callbackQueryId, 'Произошла ошибка', true);
            throw $e;
        }
    }

    protected function handleTaskAction(
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
            throw new \InvalidArgumentException('Task ID is required');
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
                $botService->answerCallbackQuery($callbackQueryId, '✅ Задача выполнена!');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;

            case 'uncomplete':
                $taskService->toggleComplete($task);
                $botService->answerCallbackQuery($callbackQueryId, '↩️ Отметка снята');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;

            case 'details':
                $this->showTaskDetails($task, $chatId, $messageId, $botService, $telegramTaskService);
                $botService->answerCallbackQuery($callbackQueryId);
                break;

            case 'delete':
                $taskService->deleteTask($task);
                $botService->answerCallbackQuery($callbackQueryId, '🗑️ Задача удалена');
                $this->refreshTasksList($chatId, $messageId, $user, $botService, $telegramTaskService);
                break;
        }
    }

    protected function handleCommandCallback(
        string $command,
        int $chatId,
        $user,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        switch ($command) {
            case 'tasks':
                $tasks = $telegramTaskService->getTasksList($user, 'active');
                $formatted = $telegramTaskService->formatTasksList($tasks, 'Все задачи');
                $botService->sendMessage(
                    $chatId,
                    $formatted['text'],
                    $formatted['keyboard'] ? $botService->createInlineKeyboard($formatted['keyboard']) : null
                );
                break;

            case 'profile':
                $this->showProfile($chatId, $user, $botService);
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

        $botService->editMessage(
            $chatId,
            $messageId,
            $formatted['text'],
            $formatted['keyboard'] ? $botService->createInlineKeyboard($formatted['keyboard']) : null
        );
    }

    protected function showTaskDetails(
        Task $task,
        int $chatId,
        int $messageId,
        TelegramBotService $botService,
        TelegramTaskService $telegramTaskService
    ): void {
        $message = $telegramTaskService->formatTaskMessage($task, true);
        $keyboard = $telegramTaskService->getTaskActionsKeyboard($task);

        $botService->editMessage(
            $chatId,
            $messageId,
            $message,
            $botService->createInlineKeyboard($keyboard)
        );
    }

    protected function showProfile(int $chatId, $user, TelegramBotService $botService): void
    {
        $totalCompleted = Task::where('user_id', $user->id)->where('completed', true)->count();
        $activeTasks = Task::where('user_id', $user->id)->where('completed', false)->count();
        $completedThisWeek = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $message = "📊 <b>Ваша статистика</b>\n\n";
        $message .= "👤 Имя: <b>{$user->name}</b>\n\n";
        $message .= "✅ Всего выполнено: <b>{$totalCompleted}</b>\n";
        $message .= "📋 Активных задач: <b>{$activeTasks}</b>\n";
        $message .= "📈 За эту неделю: <b>{$completedThisWeek}</b>\n";

        $keyboard = [
            [['text' => '📋 Все задачи', 'callback_data' => 'cmd_tasks']],
        ];

        $botService->sendMessage($chatId, $message, $botService->createInlineKeyboard($keyboard));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Failed to process callback query', [
            'callback_id' => $this->callbackData['id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
