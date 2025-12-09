<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Services\Telegram\TelegramBotService;
use Telegram\Bot\Objects\Message;

class HelpCommand implements TelegramCommandInterface
{
    public function __construct(
        protected TelegramBotService $botService
    ) {}

    public function execute(Message $message): void
    {
        $chatId = $message->getChat()->id;

        $helpText = "<b>📚 Список команд Life Dashboard</b>\n\n";
        $helpText .= "<b>Просмотр задач:</b>\n";
        $helpText .= "/tasks - Все активные задачи\n";
        $helpText .= "/today - Задачи на сегодня\n";
        $helpText .= "/completed - Выполненные за сегодня\n";
        $helpText .= "/overdue - Просроченные задачи\n\n";

        $helpText .= "<b>Управление задачами:</b>\n";
        $helpText .= "/add [название] - Быстро создать задачу\n";
        $helpText .= "/new - Создать задачу (с деталями)\n";
        $helpText .= "/complete [ID] - Отметить выполненной\n";
        $helpText .= "/delete [ID] - Удалить задачу\n";
        $helpText .= "/details [ID] - Подробности задачи\n\n";

        $helpText .= "<b>Проекты и приоритеты:</b>\n";
        $helpText .= "/projects - Список проектов\n";
        $helpText .= "/priorities - Список приоритетов\n\n";

        $helpText .= "<b>Другое:</b>\n";
        $helpText .= "/profile - Ваша статистика\n";
        $helpText .= "/help - Эта справка\n\n";

        $helpText .= "💡 <i>Совет: Используйте inline кнопки для быстрого доступа к командам!</i>";

        $this->botService->sendMessage($chatId, $helpText);
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Показать список команд';
    }
}
