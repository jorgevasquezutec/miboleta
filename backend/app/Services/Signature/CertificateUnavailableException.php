<?php

namespace App\Services\Signature;

/**
 * Hay un certificado configurado pero no se puede usar (p. ej. el binario
 * desapareció del disco). Nunca se cae en silencio al certificado global.
 */
final class CertificateUnavailableException extends \RuntimeException
{
}
