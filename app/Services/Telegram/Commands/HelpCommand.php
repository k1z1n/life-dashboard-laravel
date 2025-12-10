<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramKeyboardService;
use App\Services\Telegram\TelegramIcons;
use Telegram\Bot\Objects\Message;

class HelpCommand implements TelegramCommandInterface
{
    protected TelegramKeyboardService $keyboardService;

    public function __construct(
        protected TelegramBotService $botService
    ) {
        $this->keyboardService = new TelegramKeyboardService();
    }

    public function execute(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $this->sendHelp($chatId);
    }

    /**
     * Отправить справку
     */
    public function sendHelp(int $chatId): void
    {
        $helpText = TelegramIcons::HELP . " <b>Справка Life Dashboard</b>\n\n";

        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= TelegramIcons::TASK_LIST . " <b>ПРОСМОТР ЗАДАЧ</b>\n";
        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= TelegramIcons::TASK_LIST . " <b>Мои задачи</b> — все активные\n";
        $helpText .= TelegramIcons::TODAY . " <b>Сегодня</b> — задачи на сегодня\n";
        $helpText .= TelegramIcons::TASK_DONE . " <b>Выполненные</b> — за сегодня\n";
        $helpText .= TelegramIcons::OVERDUE . " <b>Просрочено</b> — просроченные\n\n";

        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= TelegramIcons::TASK_NEW . " <b>УПРАВЛЕНИЕ</b>\n";
        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= TelegramIcons::TASK_NEW . " <b>Создать задачу</b> — новая задача\n";
        $helpText .= TelegramIcons::PROJECT . " <b>Проекты</b> — список проектов\n";
        $helpText .= TelegramIcons::STATS . " <b>Статистика</b> — ваш профиль\n\n";

        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= TelegramIcons::BULB . " <b>СОВЕТЫ</b>\n";
        $helpText .= "━━━━━━━━━━━━━━━━━━━━\n";
        $helpText .= "• Используйте <b>кнопки внизу</b> для навигации\n";
        $helpText .= "• Нажимайте на <b>inline кнопки</b> под сообщениями\n";
        $helpText .= "• Для быстрого создания задачи:\n";
        $helpText .= "  напишите /add Название задачи\n\n";

        $helpText .= TelegramIcons::SPARKLE . " <i>Приятного использования!</i>";

        $this->botService->sendMessage(
            $chatId,
            $helpText,
            $this->keyboardService->getHelpInline()
        );
    }

    /**
     * Справка по работе с задачами
     */
    public function sendTasksHelp(int $chatId): void
    {
        $text = TelegramIcons::TASK_LIST . " <b>Работа с задачами</b>\n\n";

        $text .= "<b>Создание задачи:</b>\n";
        $text .= "• Нажмите <b>«" . TelegramIcons::TASK_NEW . " Создать задачу»</b>\n";
        $text .= "• Введите название задачи\n";
        $text .= "• Добавьте детали через кнопки\n\n";

        $text .= "<b>Быстрое создание:</b>\n";
        $text .= "<code>/add Купить молоко</code>\n\n";

        $text .= "<b>Выполнение:</b>\n";
        $text .= "• Нажмите <b>" . TelegramIcons::TASK_DONE . "</b> рядом с задачей\n";
        $text .= "• Или откройте детали и отметьте\n\n";

        $text .= "<b>Редактирование:</b>\n";
        $text .= "• Нажмите <b>" . TelegramIcons::INFO . " Детали</b>\n";
        $text .= "• Используйте кнопки для изменений";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => TelegramIcons::TASK_NEW . ' Создать задачу', 'callback_data' => 'menu_add']],
                [['text' => TelegramIcons::BACK . ' Назад к справке', 'callback_data' => 'menu_help']],
            ],
        ];

        $this->botService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Справка по проектам
     */
    public function sendProjectsHelp(int $chatId): void
    {
        $text = TelegramIcons::PROJECT . " <b>Работа с проектами</b>\n\n";

        $text .= "Проекты помогают организовать задачи.\n\n";

        $text .= "<b>Просмотр проектов:</b>\n";
        $text .= "• Нажмите <b>«" . TelegramIcons::PROJECT . " Проекты»</b>\n";
        $text .= "• Выберите проект для просмотра задач\n\n";

        $text .= "<b>Создание проекта:</b>\n";
        $text .= "• Создайте на сайте в веб-версии\n\n";

        $text .= "<b>Назначение проекта задаче:</b>\n";
        $text .= "• Откройте детали задачи\n";
        $text .= "• Нажмите <b>«" . TelegramIcons::PROJECT . " Проект»</b>\n";
        $text .= "• Выберите нужный проект";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => TelegramIcons::PROJECT . ' Мои проекты', 'callback_data' => 'menu_projects']],
                [['text' => TelegramIcons::BACK . ' Назад к справке', 'callback_data' => 'menu_help']],
            ],
        ];

        $this->botService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Справка по привязке аккаунта
     */
    public function sendLinkHelp(int $chatId): void
    {
        $text = TelegramIcons::LINK . " <b>Привязка аккаунта</b>\n\n";

        $text .= "Для работы бота нужно связать его с вашим аккаунтом на сайте.\n\n";

        $text .= "<b>Шаги:</b>\n";
        $text .= TelegramIcons::NUM_1 . " Откройте <b>" . config('app.url') . "</b>\n";
        $text .= TelegramIcons::NUM_2 . " Войдите или зарегистрируйтесь\n";
        $text .= TelegramIcons::NUM_3 . " Перейдите в <b>Профиль</b>\n";
        $text .= TelegramIcons::NUM_4 . " Нажмите <b>«Подключить Telegram»</b>\n";
        $text .= TelegramIcons::NUM_5 . " Перейдите по полученной ссылке\n\n";

        $text .= TelegramIcons::SUCCESS . " Готово! Бот привязан к вашему аккаунту.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => TelegramIcons::WEB . ' Открыть сайт', 'url' => config('app.url')]],
                [['text' => TelegramIcons::BACK . ' Назад к справке', 'callback_data' => 'menu_help']],
            ],
        ];

        $this->botService->sendMessage($chatId, $text, $keyboard);
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Показать справку';
    }
}
