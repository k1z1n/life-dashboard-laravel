<?php

namespace App\Exceptions\Ai;

use RuntimeException;

class NotificationAgentParseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $rawResponse = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}


