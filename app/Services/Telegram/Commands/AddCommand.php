<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\DTOs\TaskDTO;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\TaskService;
use Telegram\Bot\Objects\Message;

class AddCommand implements TelegramCommandInterface
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

        // Parse task title
        $parts = explode(' ', $text, 2);
        if (count($parts) < 2 || trim($parts[1]) === '') {
            $this->botService->sendMessage(
                $chatId,
                "❌ Укажите название задачи.\n\nПример: /add Купить молоко"
            );
            return;
        }

        $title = trim($parts[1]);

        // Create task
        try {
            $dto = TaskDTO::fromArray([
                'user_id' => $user->id,
                'title' => $title,
                'completed' => false,
                'tag_ids' => [],
            ]);

            $task = $this->taskService->createTask($dto);

            $this->botService->sendMessage(
                $chatId,
                "✅ <b>Задача создана!</b>\n\n" .
                "📋 {$task->title}\n" .
                "ID: {$task->id}\n\n" .
                "Хотите добавить детали?",
                $this->botService->createInlineKeyboard([
                    [
                        ['text' => '📁 Проект', 'callback_data' => "task_project_{$task->id}"],
                        ['text' => '⚡ Приоритет', 'callback_data' => "task_priority_{$task->id}"],
                    ],
                    [
                        ['text' => '📅 Срок', 'callback_data' => "task_date_{$task->id}"],
                        ['text' => '✏️ Описание', 'callback_data' => "task_description_{$task->id}"],
                    ],
                    [
                        ['text' => '✅ Готово, так оставить', 'callback_data' => 'back_tasks'],
                    ],
                ])
            );
        } catch (\Exception $e) {
            \Log::error('Error creating task via Telegram', ['error' => $e->getMessage()]);
            $this->botService->sendMessage(
                $chatId,
                "❌ Ошибка при создании задачи.\n\n" . $e->getMessage()
            );
        }
    }

    public function getName(): string
    {
        return 'add';
    }

    public function getDescription(): string
    {
        return 'Создать задачу';
    }
}
