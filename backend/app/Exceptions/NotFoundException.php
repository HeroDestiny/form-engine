<?php

namespace App\Exceptions;

use RuntimeException;

class NotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Recurso não encontrado')
    {
        parent::__construct($message);
    }
}
