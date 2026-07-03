<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\SignatureSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gestiona la configuración del certificado de firma digital ÚNICO de la
 * plataforma (.pfx/.p12), usado para firmar documentos bajo DS-009-2011-TR.
 *
 * Este servicio es AGNÓSTICO al firmador: no firma nada. Solo se encarga de
 * almacenar el certificado de forma segura (disco privado "certificates",
 * nombre no adivinable) y su configuración (password cifrada, TSA, flag de
 * activación). El pipeline de firma real (RP3-C) consumirá
 * getDecryptedPassword() y la ruta del certificado internamente.
 *
 * La password se cifra con el cast "encrypted" del modelo SignatureSettings,
 * el mismo patrón ya usado en Tenant::mail_password.
 */
class SignatureCertificateService
{
    protected const DISK = 'certificates';

    protected const ALLOWED_EXTENSIONS = ['pfx', 'p12'];

    /** Tamaño máximo del certificado en KB (10MB es más que suficiente para un .pfx). */
    protected const MAX_SIZE_KB = 10240;

    /**
     * Obtiene la configuración de firma. Solo root puede consultarla porque
     * expone certificate_subject/tsa_url (metadatos de configuración de
     * plataforma).
     *
     * @throws UnauthorizedAccessException
     */
    public function getSettings(User $user): SignatureSettings
    {
        $this->ensureRoot($user);

        return SignatureSettings::current();
    }

    /**
     * Sube y guarda un nuevo certificado de firma, reemplazando el anterior
     * si existía. Valida extensión/tamaño y, si la extensión openssl está
     * disponible, intenta abrir el .pfx con la password para dar feedback
     * temprano (best-effort: si openssl no está disponible, se omite esa
     * validación sin bloquear la carga).
     *
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException Si el archivo o la password no son válidos
     */
    public function storeCertificate(
        User $user,
        UploadedFile $file,
        string $password,
        ?string $tsaUrl = null
    ): SignatureSettings {
        $this->ensureRoot($user);
        $this->validateFile($file);

        // Validación best-effort del .pfx contra la password (feedback
        // temprano). Si openssl no está disponible se omite sin bloquear.
        $subject = $this->extractCertificateSubject($file, $password);

        $settings = SignatureSettings::current();

        // Nombre no adivinable dentro del disco privado "certificates".
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::random(40) . '.' . $extension;
        $path = $file->storeAs('', $filename, self::DISK);

        if ($path === false) {
            throw new \RuntimeException('No se pudo guardar el certificado en el disco.');
        }

        // Eliminar el binario anterior (si existía) una vez que el nuevo ya
        // se guardó correctamente, para no dejar la plataforma sin
        // certificado ante un fallo de escritura.
        $this->deleteBinary($settings->certificate_path);

        $settings->fill([
            'certificate_path' => $path,
            'certificate_password' => $password,
            'certificate_subject' => $subject,
            'tsa_url' => $tsaUrl,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
        $settings->save();

        Log::info('[SignatureCertificateService] Certificado de firma actualizado', [
            'uploaded_by' => $user->id,
        ]);

        return $settings->fresh();
    }

    /**
     * Activa/desactiva el uso de la firma con certificado de plataforma.
     * No permite activarla si no hay certificado cargado.
     *
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException
     */
    public function enableSignature(User $user, bool $enabled): SignatureSettings
    {
        $this->ensureRoot($user);

        $settings = SignatureSettings::current();

        if ($enabled && !$settings->hasCertificate()) {
            throw new InvalidArgumentException(
                'No se puede activar la firma digital: no hay un certificado cargado.'
            );
        }

        $settings->signature_enabled = $enabled;
        $settings->save();

        Log::info('[SignatureCertificateService] signature_enabled actualizado', [
            'user_id' => $user->id,
            'signature_enabled' => $enabled,
        ]);

        return $settings;
    }

    /**
     * Elimina el certificado vigente (binario + configuración asociada) y
     * desactiva la firma automáticamente.
     *
     * @throws UnauthorizedAccessException
     */
    public function deleteCertificate(User $user): SignatureSettings
    {
        $this->ensureRoot($user);

        $settings = SignatureSettings::current();
        $this->deleteBinary($settings->certificate_path);

        $settings->fill([
            'certificate_path' => null,
            'certificate_password' => null,
            'certificate_subject' => null,
            'tsa_url' => null,
            'signature_enabled' => false,
            'uploaded_by' => null,
            'uploaded_at' => null,
        ]);
        $settings->save();

        Log::info('[SignatureCertificateService] Certificado eliminado', ['user_id' => $user->id]);

        return $settings;
    }

    /**
     * Password del certificado ya desencriptada (el cast "encrypted" la
     * desencripta automáticamente al leer el atributo).
     *
     * USO INTERNO ÚNICAMENTE: pensado para el futuro pipeline de firma
     * (RP3-C). NUNCA debe exponerse a través de un controller/API.
     */
    public function getDecryptedPassword(): ?string
    {
        return SignatureSettings::current()->certificate_password;
    }

    /**
     * Ruta absoluta del certificado en disco, para uso interno del futuro
     * firmador (RP3-C). NUNCA debe exponerse a través de un controller/API.
     */
    public function getCertificateAbsolutePath(): ?string
    {
        $path = SignatureSettings::current()->certificate_path;

        if (!$path) {
            return null;
        }

        return Storage::disk(self::DISK)->path($path);
    }

    protected function ensureRoot(User $user): void
    {
        if (!$user->isRoot()) {
            throw new UnauthorizedAccessException(
                'No autorizado. Solo el administrador de plataforma puede gestionar el certificado de firma.'
            );
        }
    }

    protected function validateFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('El certificado debe ser un archivo .pfx o .p12.');
        }

        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            throw new InvalidArgumentException(
                sprintf('El certificado no puede exceder %d MB.', self::MAX_SIZE_KB / 1024)
            );
        }
    }

    /**
     * Intenta abrir el .pfx con openssl_pkcs12_read para validar la
     * password y extraer un label legible (CN del subject) para mostrar en
     * UI. Es best-effort: si la extensión openssl no está disponible en el
     * entorno, se registra un warning y se omite sin bloquear la carga. Si
     * openssl SÍ está disponible pero la password es incorrecta o el
     * archivo no es un PKCS#12 válido, se lanza una excepción para dar
     * feedback temprano al usuario root.
     *
     * @throws InvalidArgumentException
     */
    protected function extractCertificateSubject(UploadedFile $file, string $password): ?string
    {
        if (!function_exists('openssl_pkcs12_read')) {
            Log::warning('[SignatureCertificateService] openssl_pkcs12_read no disponible; se omite validación temprana del certificado');
            return null;
        }

        try {
            $raw = file_get_contents($file->getRealPath());
        } catch (\Throwable $e) {
            $raw = false;
        }

        if ($raw === false || $raw === '') {
            return null;
        }

        $certs = [];
        // @: openssl_pkcs12_read emite un warning nativo de PHP en vez de
        // una excepción cuando falla; lo silenciamos y manejamos el
        // resultado explícitamente vía su valor de retorno.
        $ok = @openssl_pkcs12_read($raw, $certs, $password);

        if (!$ok) {
            throw new InvalidArgumentException(
                'No se pudo abrir el certificado con la contraseña proporcionada. Verifique el archivo .pfx/.p12 y la contraseña.'
            );
        }

        try {
            $certData = openssl_x509_parse($certs['cert'] ?? '');
            $subject = $certData['subject'] ?? null;

            if (!$subject) {
                return null;
            }

            if (isset($subject['CN'])) {
                return $subject['CN'];
            }

            $parts = [];
            foreach ($subject as $key => $value) {
                $parts[] = "{$key}={$value}";
            }

            return implode(', ', $parts) ?: null;
        } catch (\Throwable $e) {
            Log::warning('[SignatureCertificateService] No se pudo extraer el subject del certificado', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function deleteBinary(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
