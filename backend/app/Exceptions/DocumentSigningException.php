<?php

namespace App\Exceptions;

use Exception;

/**
 * Fallo de negocio del pipeline de firma digital CRIPTOGRÁFICA (PAdES,
 * certificado de plataforma): configuración ausente/desactivada, documento
 * no elegible (huérfano, ya firmado, no requiere firma), archivo inexistente,
 * o error del sidecar `signer` (conexión, Ghostscript, pyHanko, TSA).
 *
 * NO se usa para el flujo de firma por 2FA de email (App\Services\SignatureService).
 */
class DocumentSigningException extends Exception
{
    public function __construct(string $message = "No se pudo firmar el documento", int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
