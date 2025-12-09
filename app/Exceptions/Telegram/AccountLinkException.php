<?php

namespace App\Exceptions\Telegram;

class AccountLinkException extends TelegramException
{
    protected $code = 409; // HTTP Conflict

    public function __construct(string $message = 'Failed to link Telegram account', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
