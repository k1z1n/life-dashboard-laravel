<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Models\Project;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use Telegram\Bot\Objects\Message;

class ProjectsCommand implements TelegramCommandInterface
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

        // Cache projects for 5 minutes
        $projects = \Cache::remember("user_{$user->id}_projects_with_count", 300, function () use ($user) {
            return Project::where('user_id', $user->id)
                ->withCount(['tasks' => function ($query) {
                    $query->where('completed', false);
                }])
                ->get();
        });

        if ($projects->isEmpty()) {
            $this->botService->sendMessage(
                $chatId,
                "📁 <b>Проекты</b>\n\nУ вас пока нет проектов.\n\nСоздайте проект через веб-версию."
            );
            return;
        }

        $messageText = "📁 <b>Ваши проекты ({$projects->count()})</b>\n\n";

        foreach ($projects as $index => $project) {
            $num = $index + 1;
            $tasksCount = $project->tasks_count;
            $tasksText = $tasksCount > 0 ? " ({$tasksCount} " . \Illuminate\Support\Str::plural('задача', $tasksCount) . ")" : '';
            $messageText .= "{$num}. 📁 <b>{$project->name}</b>{$tasksText}\n";
        }

        $messageText .= "\n💡 Используйте команду для просмотра задач проекта:\n";
        $messageText .= "/project [название]\n\n";
        $messageText .= "Пример: /project Работа";

        $this->botService->sendMessage($chatId, $messageText);
    }

    public function getName(): string
    {
        return 'projects';
    }

    public function getDescription(): string
    {
        return 'Список проектов';
    }
}


