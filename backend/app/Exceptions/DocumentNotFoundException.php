<?php

namespace App\Exceptions;

use Exception;

class DocumentNotFoundException extends Exception
{
    public function __construct(string $message = "Documento no encontrado", int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
