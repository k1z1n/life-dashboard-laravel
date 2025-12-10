<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Exceptions\Telegram\AccountLinkException;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramKeyboardService;
use App\Services\Telegram\TelegramIcons;
use Telegram\Bot\Objects\Message;

class StartCommand implements TelegramCommandInterface
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
            $this->sendWelcomeBack($chatId, $user);
            return;
        }

        // Show welcome message for new users
        $this->sendWelcomeNew($chatId);
    }

    /**
     * Приветствие для авторизованного пользователя
     */
    protected function sendWelcomeBack(int $chatId, $user): void
    {
        $message = TelegramIcons::WAVE . " <b>С возвращением, {$user->name}!</b>\n\n";
        $message .= "Используйте кнопки ниже для управления задачами " . TelegramIcons::ROCKET . "\n\n";
        $message .= TelegramIcons::BULB . " <i>Совет: кнопки внизу экрана всегда доступны!</i>";

        $this->botService->sendMessage(
            $chatId,
            $message,
            $this->keyboardService->getMainMenu()
        );

        // Также отправим inline меню
        $this->botService->sendMessage(
            $chatId,
            TelegramIcons::TARGET . " <b>Быстрые действия:</b>",
            $this->keyboardService->getQuickActionsInline()
        );
    }

    /**
     * Приветствие для нового пользователя
     */
    protected function sendWelcomeNew(int $chatId): void
    {
        $message = TelegramIcons::PARTY . " <b>Добро пожаловать в Life Dashboard!</b>\n\n";
        $message .= "Я помогу вам управлять задачами прямо из Telegram.\n\n";
        $message .= "<b>Для начала работы:</b>\n\n";
        $message .= TelegramIcons::NUM_1 . " Откройте <b>веб-версию</b> приложения\n";
        $message .= TelegramIcons::NUM_2 . " Перейдите в <b>Профиль</b>\n";
        $message .= TelegramIcons::NUM_3 . " Нажмите <b>«Подключить Telegram»</b>\n";
        $message .= TelegramIcons::NUM_4 . " Перейдите по полученной ссылке\n\n";
        $message .= TelegramIcons::SPARKLE . " После привязки вы получите доступ ко всем функциям!";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')],
                ],
                [
                    ['text' => TelegramIcons::HELP . ' Как привязать аккаунт?', 'callback_data' => 'help_link'],
                ],
            ],
        ];

        $this->botService->sendMessage($chatId, $message, $keyboard);
    }

    /**
     * Обработка авторизации через deep link
     */
    protected function handleAuth(int $chatId, int $telegramId, string $token, Message $message): void
    {
        $user = $this->authService->verifyAuthToken($token, $telegramId);

        if (!$user) {
            $this->botService->sendMessage(
                $chatId,
                TelegramIcons::ERROR . " <b>Ошибка привязки</b>\n\n" .
                "Код недействителен или истек.\n" .
                "Пожалуйста, получите новый код на сайте.",
                [
                    'inline_keyboard' => [
                        [['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')]],
                    ],
                ]
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

            $this->sendSuccessLink($chatId, $user);

        } catch (AccountLinkException $e) {
            \Log::channel('telegram')->warning('Account link failed', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
            ]);
            $this->botService->sendMessage(
                $chatId,
                TelegramIcons::ERROR . " <b>Ошибка привязки</b>\n\n" .
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
                TelegramIcons::ERROR . " <b>Ошибка привязки</b>\n\n" .
                "Произошла непредвиденная ошибка. Попробуйте позже."
            );
        }
    }

    /**
     * Успешная привязка аккаунта
     */
    protected function sendSuccessLink(int $chatId, $user): void
    {
        $message = TelegramIcons::PARTY . " <b>Аккаунт успешно привязан!</b>\n\n";
        $message .= "Привет, <b>{$user->name}</b>! " . TelegramIcons::WAVE . "\n\n";
        $message .= "Теперь вы можете управлять задачами через Telegram.\n";
        $message .= "Используйте кнопки ниже для навигации " . TelegramIcons::ROCKET;

        // Отправляем с Reply Keyboard (главное меню)
        $this->botService->sendMessage(
            $chatId,
            $message,
            $this->keyboardService->getMainMenu()
        );

        // И inline кнопки быстрых действий
        $this->botService->sendMessage(
            $chatId,
            TelegramIcons::TARGET . " <b>Что хотите сделать?</b>",
            $this->keyboardService->getQuickActionsInline()
        );
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
