<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramTaskService;
use Telegram\Bot\Objects\Message;

class OverdueCommand implements TelegramCommandInterface
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

        $user = $this->authService->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                "❌ Аккаунт не привязан.\n\nИспользуйте /start для привязки."
            );
            return;
        }

        $tasks = $this->taskService->getTasksList($user, 'overdue');
        $formatted = $this->taskService->formatTasksList($tasks, '⚠️ Просроченные задачи');

        $this->botService->sendMessage(
            $chatId,
            $formatted['text'],
            $formatted['keyboard'] ? $this->botService->createInlineKeyboard($formatted['keyboard']) : null
        );
    }

    public function getName(): string
    {
        return 'overdue';
    }

    public function getDescription(): string
    {
        return 'Просроченные задачи';
    }
}
