<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramCommandInterface;
use App\Services\Telegram\Commands\AddCommand;
use App\Services\Telegram\Commands\CompleteCommand;
use App\Services\Telegram\Commands\CompletedCommand;
use App\Services\Telegram\Commands\DetailsCommand;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\Telegram\Commands\OverdueCommand;
use App\Services\Telegram\Commands\PrioritiesCommand;
use App\Services\Telegram\Commands\ProfileCommand;
use App\Services\Telegram\Commands\ProjectsCommand;
use App\Services\Telegram\Commands\StartCommand;
use App\Services\Telegram\Commands\TasksCommand;
use App\Services\Telegram\Commands\TodayCommand;
use App\Services\TaskService;
use Telegram\Bot\Objects\Message;

class CommandHandler
{
    protected array $commands = [];

    public function __construct(
        protected TelegramBotService $botService,
        protected TelegramAuthService $authService,
        protected TelegramTaskService $taskService,
        protected TaskService $realTaskService
    ) {
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands = [
            'start' => new StartCommand($this->botService, $this->authService),
            'help' => new HelpCommand($this->botService),
            'tasks' => new TasksCommand($this->botService, $this->authService, $this->taskService),
            'today' => new TodayCommand($this->botService, $this->authService, $this->taskService),
            'completed' => new CompletedCommand($this->botService, $this->authService, $this->taskService),
            'overdue' => new OverdueCommand($this->botService, $this->authService, $this->taskService),
            'add' => new AddCommand($this->botService, $this->authService, $this->realTaskService),
            'complete' => new CompleteCommand($this->botService, $this->authService, $this->realTaskService),
            'details' => new DetailsCommand($this->botService, $this->authService, $this->taskService),
            'projects' => new ProjectsCommand($this->botService, $this->authService),
            'priorities' => new PrioritiesCommand($this->botService, $this->authService),
            'profile' => new ProfileCommand($this->botService, $this->authService),
        ];
    }

    public function handle(Message $message): void
    {
        $text = $message->getText();

        // Check if it's a command
        if (!str_starts_with($text, '/')) {
            return;
        }

        // Parse command
        $parts = explode(' ', $text);
        $commandName = ltrim($parts[0], '/');
        $commandName = explode('@', $commandName)[0]; // Remove bot username if present

        // Execute command
        if (isset($this->commands[$commandName])) {
            $this->commands[$commandName]->execute($message);
        } else {
            $this->handleUnknownCommand($message);
        }
    }

    protected function handleUnknownCommand(Message $message): void
    {
        $chatId = $message->getChat()->id;
        $this->botService->sendMessage(
            $chatId,
            "❓ Неизвестная команда.\n\nИспользуйте /help для списка доступных команд."
        );
    }

    public function getCommand(string $name): ?TelegramCommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    public function getAllCommands(): array
    {
        return $this->commands;
    }
}
