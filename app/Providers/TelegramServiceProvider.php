<?php

namespace App\Providers;

use App\Services\Telegram\CommandHandler;
use App\Services\Telegram\ConversationManager;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramTaskService;
use App\Services\TaskService;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Telegram services as singletons
        $this->app->singleton(TelegramBotService::class);
        $this->app->singleton(TelegramAuthService::class);
        $this->app->singleton(TelegramTaskService::class);
        $this->app->singleton(ConversationManager::class);

        // Register CommandHandler with dependencies
        $this->app->singleton(CommandHandler::class, function ($app) {
            return new CommandHandler(
                $app->make(TelegramBotService::class),
                $app->make(TelegramAuthService::class),
                $app->make(TelegramTaskService::class),
                $app->make(TaskService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register Telegram configuration
        $this->mergeConfigFrom(
            config_path('telegram.php'), 'telegram'
        );
    }
}
