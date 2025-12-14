<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Models\Priority;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use Telegram\Bot\Objects\Message;

class PrioritiesCommand implements TelegramCommandInterface
{
    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService
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

        // Cache priorities for 5 minutes
        $priorities = \Cache::remember("user_{$user->id}_priorities_with_count", 300, function () use ($user) {
            return Priority::where('user_id', $user->id)
                ->withCount(['tasks' => function ($query) {
                    $query->where('completed', false);
                }])
                ->orderBy('order', 'desc')
                ->get();
        });

        if ($priorities->isEmpty()) {
            $this->botService->sendMessage(
                $chatId,
                "⚡ <b>Приоритеты</b>\n\nУ вас пока нет приоритетов.\n\nСоздайте приоритет через веб-версию."
            );
            return;
        }

        $messageText = "⚡ <b>Ваши приоритеты ({$priorities->count()})</b>\n\n";

        foreach ($priorities as $priority) {
            $icon = match($priority->order) {
                3 => '🔴',
                2 => '🟡',
                1 => '🟢',
                default => '⚪'
            };
            $tasksCount = $priority->tasks_count;
            $tasksText = $tasksCount > 0 ? " ({$tasksCount})" : '';
            $messageText .= "{$icon} <b>{$priority->name}</b>{$tasksText}\n";
        }

        $this->botService->sendMessage($chatId, $messageText);
    }

    public function getName(): string
    {
        return 'priorities';
    }

    public function getDescription(): string
    {
        return 'Список приоритетов';
    }
}


