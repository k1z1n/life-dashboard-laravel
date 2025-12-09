<?php

namespace App\Exceptions\Telegram;

class UnauthorizedException extends TelegramException
{
    protected $code = 401; // HTTP код
    protected $message = 'Unauthorized: Telegram account not linked';
}
