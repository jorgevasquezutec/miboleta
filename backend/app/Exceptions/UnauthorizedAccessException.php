<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedAccessException extends Exception
{
    public function __construct(string $message = "No autorizado", int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
