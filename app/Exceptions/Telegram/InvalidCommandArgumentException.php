<?php

namespace App\Exceptions\Telegram;

class InvalidCommandArgumentException extends TelegramException
{
    protected $code = 400; // HTTP Bad Request

    public function __construct(string $message = 'Invalid command arguments', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
