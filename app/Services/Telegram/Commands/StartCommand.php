<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Exceptions\Telegram\AccountLinkException;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use Telegram\Bot\Objects\Message;

class StartCommand implements TelegramCommandInterface
{
    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService
    ) {}

    public function execute(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $telegramId = $message->getFrom()->id;
        $text = $message->getText();

        // Check if this is auth deep link
        $parts = explode(' ', $text);
        if (count($parts) === 2 && strlen($parts[1]) > 10) {
            $token = $parts[1];
            $this->handleAuth($chatId, $telegramId, $token, $message);
            return;
        }

        // Check if already linked
        if ($this->authService->isLinked($telegramId)) {
            $user = $this->authService->getUserByTelegramId($telegramId);
            $this->botService->sendMessage(
                $chatId,
                "👋 С возвращением, <b>{$user->name}</b>!\n\n" .
                "Ваш аккаунт уже привязан.\n" .
                "Используйте /help для списка команд.",
                $this->getMainKeyboard()
            );
            return;
        }

        // Show welcome message
        $this->botService->sendMessage(
            $chatId,
            "🎉 <b>Добро пожаловать в Life Dashboard!</b>\n\n" .
            "Для начала работы необходимо привязать ваш аккаунт.\n\n" .
            "📱 Откройте веб-версию приложения\n" .
            "👤 Перейдите в профиль\n" .
            "🔗 Нажмите «Подключить Telegram»\n" .
            "✅ Перейдите по ссылке\n\n" .
            "После привязки вы сможете управлять задачами прямо из Telegram!"
        );
    }

    protected function handleAuth(int $chatId, int $telegramId, string $token, Message $message): void
    {
        $user = $this->authService->verifyAuthToken($token, $telegramId);

        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                "❌ <b>Ошибка привязки</b>\n\n" .
                "Код недействителен или истек.\n" .
                "Пожалуйста, получите новый код на сайте."
            );
            return;
        }

        // Link account
        try {
            $this->authService->linkAccount($user->id, [
                'telegram_id' => $telegramId,
                'telegram_username' => $message->getFrom()->username,
                'telegram_first_name' => $message->getFrom()->firstName,
                'telegram_last_name' => $message->getFrom()->lastName,
                'chat_id' => $chatId,
            ]);

            $this->botService->sendMessage(
                $chatId,
                "🎉 <b>Аккаунт успешно привязан!</b>\n\n" .
                "Привет, <b>{$user->name}</b>!\n\n" .
                "Теперь вы можете управлять задачами через Telegram.\n" .
                "Используйте /help для списка команд.",
                $this->getMainKeyboard()
            );
        } catch (AccountLinkException $e) {
            \Log::channel('telegram')->warning('Account link failed', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
            ]);
            $this->botService->sendMessage(
                $chatId,
                "❌ <b>Ошибка привязки</b>\n\n" .
                "Этот Telegram аккаунт уже привязан к другому пользователю.\n\n" .
                "Сначала отвяжите его от другого аккаунта."
            );
        } catch (\Exception $e) {
            \Log::channel('telegram')->error('Unexpected error during account linking', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
            ]);
            $this->botService->sendMessage(
                $chatId,
                "❌ <b>Ошибка привязки</b>\n\n" .
                "Произошла непредвиденная ошибка. Попробуйте позже."
            );
        }
    }

    protected function getMainKeyboard(): array
    {
        return $this->botService->createInlineKeyboard([
            [
                ['text' => '📋 Мои задачи', 'callback_data' => 'cmd_tasks'],
                ['text' => '➕ Создать задачу', 'callback_data' => 'cmd_new'],
            ],
            [
                ['text' => '📊 Статистика', 'callback_data' => 'cmd_profile'],
                ['text' => '❓ Помощь', 'callback_data' => 'cmd_help'],
            ],
        ]);
    }

    public function getName(): string
    {
        return 'start';
    }

    public function getDescription(): string
    {
        return 'Начать работу с ботом';
    }
}
