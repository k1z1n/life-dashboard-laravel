<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

/**
 * Регистрация команд бота в Telegram (меню команд)
 */
class TelegramSetCommands extends Command
{
    protected $signature = 'telegram:set-commands';
    protected $description = 'Зарегистрировать команды бота в Telegram (меню /)';

    public function handle(): int
    {
        $telegram = new Api(config('telegram.bot_token'));

        // Список команд для меню
        $commands = [
            [
                'command' => 'start',
                'description' => '🚀 Начать работу / Привязать аккаунт',
            ],
            [
                'command' => 'menu',
                'description' => '📱 Главное меню',
            ],
            [
                'command' => 'tasks',
                'description' => '📋 Все мои задачи',
            ],
            [
                'command' => 'today',
                'description' => '🌅 Задачи на сегодня',
            ],
            [
                'command' => 'add',
                'description' => '➕ Создать задачу (+ название)',
            ],
            [
                'command' => 'completed',
                'description' => '✅ Выполненные задачи',
            ],
            [
                'command' => 'overdue',
                'description' => '⚠️ Просроченные задачи',
            ],
            [
                'command' => 'projects',
                'description' => '📁 Мои проекты',
            ],
            [
                'command' => 'profile',
                'description' => '📊 Статистика профиля',
            ],
            [
                'command' => 'help',
                'description' => '❓ Справка и помощь',
            ],
        ];

        try {
            $result = $telegram->setMyCommands([
                'commands' => json_encode($commands),
            ]);

            if ($result) {
                $this->info('✅ Команды успешно зарегистрированы в Telegram!');
                $this->newLine();
                $this->table(
                    ['Команда', 'Описание'],
                    array_map(fn($cmd) => ['/' . $cmd['command'], $cmd['description']], $commands)
                );
                return self::SUCCESS;
            }

            $this->error('❌ Не удалось зарегистрировать команды');
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('❌ Ошибка: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

