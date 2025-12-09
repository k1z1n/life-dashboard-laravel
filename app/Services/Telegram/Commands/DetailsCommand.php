<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Models\Task;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramTaskService;
use Telegram\Bot\Objects\Message;

class DetailsCommand implements TelegramCommandInterface
{
    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService,
        protected TelegramTaskService $taskService
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
                "❌ Укажите ID задачи.\n\nПример: /details 123"
            );
            return;
        }

        $taskId = (int) $parts[1];

        // Find task
        $task = Task::where('id', $taskId)
            ->where('user_id', $user->id)
            ->with(['priority', 'project', 'tags'])
            ->first();

        if (!$task) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Задача с ID {$taskId} не найдена."
            );
            return;
        }

        $messageText = $this->taskService->formatTaskMessage($task, true);
        $keyboard = $this->taskService->getTaskActionsKeyboard($task);

        $this->botService->sendMessage(
            $chatId,
            $messageText,
            $this->botService->createInlineKeyboard($keyboard)
        );
    }

    public function getName(): string
    {
        return 'details';
    }

    public function getDescription(): string
    {
        return 'Показать детали задачи';
    }
}
