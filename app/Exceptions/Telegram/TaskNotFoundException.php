<?php

namespace App\Exceptions\Telegram;

class TaskNotFoundException extends TelegramException
{
    protected $code = 404; // HTTP код

    public function __construct(string $message = 'Task not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
