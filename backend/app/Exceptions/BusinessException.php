<?php

namespace App\Exceptions;

use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid_operation', public readonly ?array $errors = null)
    {
        parent::__construct($message);
    }
}
