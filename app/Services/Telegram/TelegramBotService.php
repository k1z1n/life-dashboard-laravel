<?php

namespace App\Services\Telegram;

use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramBotService
{
    protected Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    /**
     * Send message to chat
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): Message
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard) {
            $params['reply_markup'] = Keyboard::make($keyboard);
        }

        return $this->telegram->sendMessage($params);
    }

    /**
     * Edit message
     */
    public function editMessage(int $chatId, int $messageId, string $text, ?array $keyboard = null): Message
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard) {
            $params['reply_markup'] = Keyboard::make($keyboard);
        }

        return $this->telegram->editMessageText($params);
    }

    /**
     * Delete message
     */
    public function deleteMessage(int $chatId, int $messageId): bool
    {
        return $this->telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ];

        if ($text) {
            $params['text'] = $text;
        }

        return $this->telegram->answerCallbackQuery($params);
    }

    /**
     * Create inline keyboard
     */
    public function createInlineKeyboard(array $buttons): array
    {
        return [
            'inline_keyboard' => $buttons,
        ];
    }

    /**
     * Create reply keyboard
     */
    public function createReplyKeyboard(array $buttons, bool $resize = true, bool $oneTime = false): array
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
        ];
    }

    /**
     * Remove keyboard
     */
    public function removeKeyboard(): array
    {
        return [
            'remove_keyboard' => true,
        ];
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, ?string $secretToken = null): mixed
    {
        $params = [
            'url' => $url,
            'allowed_updates' => config('telegram.webhook.allowed_updates'),
            'max_connections' => config('telegram.webhook.max_connections'),
        ];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        return $this->telegram->setWebhook($params);
    }

    /**
     * Remove webhook
     */
    public function removeWebhook(): mixed
    {
        return $this->telegram->removeWebhook();
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): mixed
    {
        return $this->telegram->getWebhookInfo();
    }

    /**
     * Get bot info
     */
    public function getBotInfo(): array
    {
        return $this->telegram->getMe();
    }
}
