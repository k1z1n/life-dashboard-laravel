<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Models\Task;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\TaskService;
use Telegram\Bot\Objects\Message;

class CompleteCommand implements TelegramCommandInterface
{
    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService,
        protected TaskService $taskService
    ) {}

    public function execute(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $telegramId = $message->getFrom()->id;
        $text = $message->getText();

        $user = $this->authService->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Аккаунт не привязан.\n\nИспользуйте /start для привязки."
            );
            return;
        }

        // Parse task ID
        $parts = explode(' ', $text);
        if (count($parts) < 2 || !is_numeric($parts[1])) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Укажите ID задачи.\n\nПример: /complete 123"
            );
            return;
        }

        $taskId = (int) $parts[1];

        // Find task
        $task = Task::where('id', $taskId)
            ->where('user_id', $user->id)
            ->first();

        if (!$task) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Задача с ID {$taskId} не найдена."
            );
            return;
        }

        // Toggle complete
        try {
            if ($task->completed) {
                $this->taskService->toggleComplete($task);
                $this->botService->sendMessage(
                    $chatId,
                    "↩️ <b>Отметка выполнения снята</b>\n\n" .
                    "📋 {$task->title}",
                    $this->botService->createInlineKeyboard([
                        [
                            ['text' => '✅ Выполнить снова', 'callback_data' => "task_complete_{$task->id}"],
                            ['text' => '📋 К списку', 'callback_data' => 'cmd_tasks'],
                        ],
                    ])
                );
            } else {
                $this->taskService->toggleComplete($task);
                $completedAt = now()->locale('ru')->isoFormat('HH:mm');

                $this->botService->sendMessage(
                    $chatId,
                    "✅ <b>Задача выполнена!</b>\n\n" .
                    "📋 {$task->title}\n" .
                    "✓ Выполнено в {$completedAt}\n\n" .
                    "Отличная работа! 🎉",
                    $this->botService->createInlineKeyboard([
                        [
                            ['text' => '↩️ Отменить', 'callback_data' => "task_uncomplete_{$task->id}"],
                            ['text' => '📋 К списку', 'callback_data' => 'cmd_tasks'],
                        ],
                    ])
                );
            }
        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Error toggling task completion', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
            $this->botService->sendMessage(
                $chatId,
                "❌ Ошибка при изменении статуса задачи.\n\nПопробуйте позже."
            );
        }
    }

    public function getName(): string
    {
        return 'complete';
    }

    public function getDescription(): string
    {
        return 'Отметить задачу выполненной';
    }
}


