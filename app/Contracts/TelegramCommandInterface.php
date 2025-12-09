<?php

namespace App\Contracts;

use Telegram\Bot\Objects\Message;

interface TelegramCommandInterface
{
    /**
     * Execute the command
     */
    public function execute(Message $message): void;

    /**
     * Get command name
     */
    public function getName(): string;

    /**
     * Get command description
     */
    public function getDescription(): string;
}
