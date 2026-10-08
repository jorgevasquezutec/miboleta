<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\TenantSignatureCertificate;
use App\Models\User;
use App\Services\Signature\CertificateInspector;
use App\Services\Signature\CertificateUnavailableException;
use App\Services\Signature\EffectiveCertificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gestiona los certificados de firma digital (.pfx/.p12) usados para firmar
 * documentos bajo DS-009-2011-TR: el certificado GLOBAL de la plataforma
 * (por defecto) y, opcionalmente, uno PROPIO por empresa. Al firmar, se usa
 * el de la empresa y, si no tiene, el global (ver resolveForTenant()).
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
    public function __construct(
        protected AuditService $auditService,
        protected CertificateInspector $inspector
    ) {
    }

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
        $info = $this->inspector->inspect($file, $password);

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
            'certificate_subject' => $info->subject,
            'certificate_ruc' => $info->ruc,
            'certificate_organization' => $info->organization,
            'certificate_expires_at' => $info->expiresAt,
            'tsa_url' => $tsaUrl,
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
        $settings->save();

        $this->auditService->logCertificateUploaded($user->id);

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

        $this->auditService->logSignatureSettingsUpdated($user->id, ['signature_enabled' => $enabled]);

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
            'certificate_ruc' => null,
            'certificate_organization' => null,
            'certificate_expires_at' => null,
            'tsa_url' => null,
            'signature_enabled' => false,
            'uploaded_by' => null,
            'uploaded_at' => null,
        ]);
        $settings->save();

        $this->auditService->logCertificateDeleted($user->id);

        Log::info('[SignatureCertificateService] Certificado eliminado', ['user_id' => $user->id]);

        return $settings;
    }

    /**
     * Password del certificado ya desencriptada (el cast "encrypted" la
     * desencripta automáticamente al leer el atributo).
     *
     * Solo del certificado GLOBAL. USO INTERNO ÚNICAMENTE: NUNCA debe
     * exponerse a través de un controller/API.
     */
    public function getDecryptedPassword(): ?string
    {
        return SignatureSettings::current()->certificate_password;
    }

    /**
     * Ruta absoluta del certificado GLOBAL en disco. USO INTERNO: NUNCA debe
     * exponerse a través de un controller/API.
     */
    public function getCertificateAbsolutePath(): ?string
    {
        $path = SignatureSettings::current()->certificate_path;

        if (!$path) {
            return null;
        }

        return Storage::disk(self::DISK)->path($path);
    }

    // ============ Certificado por empresa ============

    /**
     * Lista todas las empresas con el estado de su certificado de firma.
     * Solo root. Filtros: 'search' (nombre|RUC|razón social), 'only_warnings'.
     *
     * @return Collection<int, array>
     * @throws UnauthorizedAccessException
     */
    public function listTenantCertificates(User $user, array $filters = []): Collection
    {
        $this->ensureRoot($user);

        $globalHas = SignatureSettings::current()->hasCertificate();

        $query = Tenant::query()->with('signatureCertificate.uploadedBy')->orderBy('name');

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('ruc', 'like', $term)
                    ->orWhere('business_name', 'like', $term);
            });
        }

        $items = $query->get()->map(fn (Tenant $t) => $this->transformTenantCertificate($t, $globalHas));

        if (!empty($filters['only_warnings'])) {
            $items = $items->filter(fn (array $i) => count($i['warnings']) > 0)->values();
        }

        return $items->values();
    }

    /**
     * Guarda (o reemplaza) el certificado propio de una empresa. NUNCA se
     * rechaza por un RUC que no coincida: solo se avisa.
     *
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException Si el archivo o la password no son válidos
     */
    public function storeTenantCertificate(User $user, Tenant $tenant, UploadedFile $file, string $password, ?string $tsaUrl = null): TenantSignatureCertificate
    {
        $this->ensureRoot($user);
        $this->validateFile($file);

        $info = $this->inspector->inspect($file, $password);

        $extension = strtolower($file->getClientOriginalExtension());
        $newPath = $file->storeAs('', Str::random(40) . '.' . $extension, self::DISK);

        if ($newPath === false) {
            throw new \RuntimeException('No se pudo guardar el certificado en el disco.');
        }

        $record = TenantSignatureCertificate::firstOrNew(['tenant_id' => $tenant->id]);
        $old = $record->exists ? $record->certificate_path : null;

        try {
            $record->fill([
                'certificate_path' => $newPath,
                'certificate_password' => $password,
                'certificate_subject' => $info->subject,
                'certificate_ruc' => $info->ruc,
                'certificate_organization' => $info->organization,
                'certificate_expires_at' => $info->expiresAt,
                'tsa_url' => ($tsaUrl !== null && trim($tsaUrl) !== '') ? trim($tsaUrl) : null,
                'uploaded_by' => $user->id,
                'uploaded_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            // No dejar el binario nuevo huérfano.
            Storage::disk(self::DISK)->delete($newPath);
            throw $e;
        }

        // El binario anterior se borra SOLO tras guardar con éxito.
        if ($old && $old !== $newPath) {
            $this->deleteBinary($old);
        }

        $this->auditService->logTenantCertificateUploaded($user->id, $tenant, [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'certificate_ruc' => $record->certificate_ruc,
            'certificate_organization' => $record->certificate_organization,
            'certificate_expires_at' => $record->certificate_expires_at?->toIso8601String(),
            'ruc_mismatch' => $record->rucMatches($tenant->ruc) === false,
            'replaced' => $old !== null,
            'tsa_url' => $record->tsa_url,
        ]);

        Log::info('[SignatureCertificateService] Certificado de empresa actualizado', [
            'tenant_id' => $tenant->id,
            'uploaded_by' => $user->id,
        ]);

        return $record;
    }

    /**
     * Elimina el certificado propio de la empresa (vuelve al global).
     *
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException Si la empresa no tiene certificado propio
     */
    public function deleteTenantCertificate(User $user, Tenant $tenant): void
    {
        $this->ensureRoot($user);

        $record = $tenant->signatureCertificate;

        if (!$record) {
            throw new InvalidArgumentException('La empresa no tiene certificado propio.');
        }

        $this->deleteBinary($record->certificate_path);
        $record->delete();

        $this->auditService->logTenantCertificateDeleted($user->id, $tenant);

        Log::info('[SignatureCertificateService] Certificado de empresa eliminado', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Certificado efectivo para firmar los documentos de una empresa: el
     * propio si existe; si no, el global; si no hay ninguno, null.
     *
     * Si hay uno configurado pero su binario falta, lanza excepción: NUNCA
     * se cae en silencio al global (firmaría con el certificado equivocado).
     * USO INTERNO: no exponer por API.
     *
     * @throws CertificateUnavailableException
     */
    public function resolveForTenant(?int $tenantId): ?EffectiveCertificate
    {
        $disk = Storage::disk(self::DISK);

        if ($tenantId) {
            $record = TenantSignatureCertificate::where('tenant_id', $tenantId)->first();

            if ($record) {
                if (empty($record->certificate_path)
                    || $record->certificate_password === null
                    || !$disk->exists($record->certificate_path)) {
                    throw new CertificateUnavailableException(
                        'El certificado de firma de la empresa no está disponible en el servidor; vuelva a cargarlo.'
                    );
                }

                return new EffectiveCertificate(
                    'tenant',
                    $disk->path($record->certificate_path),
                    $record->certificate_password,
                    $record->certificate_subject,
                    $record->certificate_ruc,
                    $record->certificate_organization,
                    $record->certificate_expires_at,
                    $record->tsa_url,
                );
            }
        }

        $settings = SignatureSettings::current();

        if (!$settings->hasCertificate()) {
            return null;
        }

        if ($settings->certificate_password === null || !$disk->exists($settings->certificate_path)) {
            throw new CertificateUnavailableException(
                'El certificado de firma global no está disponible en el servidor; vuelva a cargarlo.'
            );
        }

        return new EffectiveCertificate(
            'global',
            $disk->path($settings->certificate_path),
            $settings->certificate_password,
            $settings->certificate_subject,
            $settings->certificate_ruc,
            $settings->certificate_organization,
            $settings->certificate_expires_at,
            $settings->tsa_url,
        );
    }

    /**
     * Item del contrato GET /signature/tenants. Nunca incluye password ni ruta.
     */
    public function transformTenantCertificate(Tenant $tenant, bool $globalHasCertificate): array
    {
        $record = $tenant->signatureCertificate;

        if ($record) {
            $source = 'tenant';
            $warnings = $record->warnings($tenant->ruc);
            $mismatch = $record->rucMatches($tenant->ruc) === false;
            $certificate = [
                'certificate_subject' => $record->certificate_subject,
                'certificate_ruc' => $record->certificate_ruc,
                'certificate_organization' => $record->certificate_organization,
                'certificate_expires_at' => $record->certificate_expires_at,
                'tsa_url' => $record->tsa_url,
                'uploaded_at' => $record->uploaded_at,
                'uploaded_by' => $record->uploadedBy
                    ? ['id' => $record->uploadedBy->id, 'name' => $record->uploadedBy->name]
                    : null,
            ];
        } else {
            $source = $globalHasCertificate ? 'global' : 'none';
            $mismatch = false;
            $certificate = null;
            $warnings = $source === 'none'
                ? [[
                    'code' => 'no_certificate',
                    'message' => 'La empresa no tiene certificado propio y no hay certificado global configurado.',
                ]]
                : [];
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_ruc' => $tenant->ruc,
            'tenant_business_name' => $tenant->business_name,
            'tenant_status' => $tenant->status,
            'certificate_source' => $source,
            'has_own_certificate' => $record !== null,
            'certificate' => $certificate,
            'ruc_mismatch' => $mismatch,
            'warnings' => $warnings,
        ];
    }

    public function ensureRoot(User $user): void
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

    protected function deleteBinary(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
