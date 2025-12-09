<?php

namespace App\Services\Telegram\Commands;

use App\Contracts\TelegramCommandInterface;
use App\Models\Task;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use Telegram\Bot\Objects\Message;

class ProfileCommand implements TelegramCommandInterface
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

        // Get statistics
        $totalCompleted = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $activeTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->count();

        $completedThisWeek = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $completedThisMonth = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->count();

        $overdueTasks = Task::where('user_id', $user->id)
            ->where('completed', false)
            ->whereDate('due_date', '<', today())
            ->count();

        $messageText = "📊 <b>Ваша статистика</b>\n\n";
        $messageText .= "👤 Имя: <b>{$user->name}</b>\n\n";
        $messageText .= "✅ Всего выполнено: <b>{$totalCompleted}</b>\n";
        $messageText .= "📋 Активных задач: <b>{$activeTasks}</b>\n";
        $messageText .= "⚠️ Просроченных: <b>{$overdueTasks}</b>\n\n";
        $messageText .= "📈 За эту неделю: <b>{$completedThisWeek}</b>\n";
        $messageText .= "📈 За этот месяц: <b>{$completedThisMonth}</b>\n";

        if ($completedThisWeek > 0) {
            $streak = $this->calculateStreak($user->id);
            if ($streak > 1) {
                $messageText .= "\n🔥 Streak: <b>{$streak} дня подряд!</b>";
            }
        }

        $keyboard = [
            [
                ['text' => '📋 Все задачи', 'callback_data' => 'cmd_tasks'],
                ['text' => '📅 На сегодня', 'callback_data' => 'cmd_today'],
            ],
            [
                ['text' => '🌐 Открыть веб-версию', 'url' => config('app.url') . '/profile'],
            ],
        ];

        $this->botService->sendMessage(
            $chatId,
            $messageText,
            $this->botService->createInlineKeyboard($keyboard)
        );
    }

    protected function calculateStreak(int $userId): int
    {
        $streak = 1;
        $currentDate = today();

        while (true) {
            $hasCompletedTasks = Task::where('user_id', $userId)
                ->where('completed', true)
                ->whereDate('completed_at', $currentDate)
                ->exists();

            if (!$hasCompletedTasks) {
                break;
            }

            $streak++;
            $currentDate = $currentDate->subDay();

            // Limit to 30 days to avoid infinite loop
            if ($streak > 30) {
                break;
            }
        }

        return $streak - 1; // Subtract 1 because we start with 1
    }

    public function getName(): string
    {
        return 'profile';
    }

    public function getDescription(): string
    {
        return 'Показать статистику';
    }
}
