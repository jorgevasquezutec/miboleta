<?php

namespace App\Exceptions;

use Exception;

class UserCreationException extends Exception
{
    public function __construct(string $message = "Error al crear usuario", int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
