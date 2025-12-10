<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramKeyboardService;
use App\Services\Telegram\TelegramIcons;
use Telegram\Bot\Objects\Message;

/**
 * Команда /menu — показать главное меню
 */
class MenuCommand implements TelegramCommandInterface
{
    protected TelegramKeyboardService $keyboardService;

    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService
    ) {
        $this->keyboardService = new TelegramKeyboardService();
    }

    public function execute(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $telegramId = $message->getFrom()->id;

        // Проверка авторизации
        $user = $this->authService->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                TelegramIcons::ERROR . " Аккаунт не привязан.\n\nИспользуйте /start для привязки."
            );
            return;
        }

        $this->showMainMenu($chatId, $user);
    }

    /**
     * Показать главное меню
     */
    public function showMainMenu(int $chatId, $user): void
    {
        $message = TelegramIcons::HOME . " <b>Главное меню</b>\n\n";
        $message .= "Привет, <b>{$user->name}</b>! " . TelegramIcons::WAVE . "\n\n";
        $message .= "Выберите действие из меню ниже " . TelegramIcons::ROCKET;

        $this->botService->sendMessage(
            $chatId,
            $message,
            $this->keyboardService->getMainMenu()
        );

        // Inline меню для быстрых действий
        $this->botService->sendMessage(
            $chatId,
            TelegramIcons::TARGET . " <b>Быстрые действия:</b>",
            $this->keyboardService->getQuickActionsInline()
        );
    }

    public function getName(): string
    {
        return 'menu';
    }

    public function getDescription(): string
    {
        return 'Показать главное меню';
    }
}

