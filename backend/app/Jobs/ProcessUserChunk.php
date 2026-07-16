<?php

namespace App\Jobs;

use App\Models\Role;
use App\Models\User;
use App\Models\UserBatch;
use App\Services\UserService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUserChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos por chunk
    public $tries = 1; // Sin reintentos - falla inmediatamente si hay error

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $batchUuid,
        private array $users,
        private int $chunkNumber
    ) {
        // Queue específica para bulk uploads
        $this->onQueue('bulk-uploads');
    }

    /**
     * Execute the job.
     */
    public function handle(UserService $userService): void
    {
        // ✅ Verificar si el batch fue cancelado
        if ($this->batch()->cancelled()) {
            Log::info("⏸️  [ProcessUserChunk] Batch cancelled, skipping chunk", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
            ]);
            return;
        }

        // 📦 Cargar el batch una sola vez para leer sus processing_options
        // (send_welcome_emails). Ver UserBatchController@store / @uploadData,
        // que las guarda al crear el batch.
        $batch = UserBatch::where('uuid', $this->batchUuid)->first();
        $processingOptions = $batch?->processing_options ?? [];
        $sendWelcomeEmail = (bool) ($processingOptions['send_welcome_emails'] ?? true);

        // 🔐 FIX B2-r1: quién creó el batch (UserBatch::created_by_user_id,
        // guardado por UserBatchController@store/@uploadData). Se usa para
        // rechazar filas que asignen usuarios a empresas que el creador no
        // administra (ver tenantOwnershipError). Si no se puede resolver el
        // creador, se trata como NO-root (restrictivo por defecto).
        $creator = $batch?->createdBy;
        $creatorIsRoot = $creator?->isRoot() ?? false;

        Log::info("🔄 [ProcessUserChunk] Processing chunk", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'users_count' => count($this->users),
            'send_welcome_email' => $sendWelcomeEmail,
        ]);

        $created = 0;
        $errors = [];
        $affectedTenantIds = [];

        DB::beginTransaction();

        try {
            foreach ($this->users as $userData) {
                try {
                    // 🔐 FIX B2-r1: rechazar la fila si alguna de sus
                    // organizaciones referencia un tenant que el creador del
                    // batch no administra. La resolución de ruc -> tenant_id
                    // ya ocurrió aguas arriba (UsersImport::parseOrganizations
                    // para el path de archivo, BulkUserUploadService::
                    // transformOrganizationsForProcessing para el path de
                    // datos editados/JSON), pero ninguna de esas dos vías
                    // conoce quién subió el batch, así que la garantía dura
                    // vive aquí, en el worker (fail-closed): sin este
                    // chequeo, un admin/admin_tenant de la Empresa A podía
                    // incluir en su archivo/grid una fila con el RUC de la
                    // Empresa B (ajena) y el usuario terminaba creado y
                    // adjunto a esa empresa ajena.
                    $ownershipError = $this->tenantOwnershipError(
                        $userData['organizaciones'] ?? [],
                        $creator,
                        $creatorIsRoot
                    );
                    if ($ownershipError !== null) {
                        throw new \Exception($ownershipError);
                    }

                    // La carga masiva SOLO crea usuarios: no actualiza a los
                    // que ya existen. Normalmente una fila así no llega hasta
                    // aquí, porque la validación previa la marca como error y
                    // el batch ni se crea (ver BulkUserUploadService::
                    // validateFile/validateData y UserBatchController, que
                    // corta con 422). Si llega, es por una carrera —alguien
                    // creó ese usuario entre la previsualización y el
                    // procesamiento en cola—, así que se reporta como fila
                    // fallida en vez de omitirse en silencio.
                    $existingUser = $this->findExistingUser($userData);

                    if ($existingUser) {
                        throw new \Exception(
                            'El usuario ya existe en el sistema (documento o email en uso); '
                            . 'la carga masiva solo da de alta usuarios nuevos.'
                        );
                    }

                    // Preparar datos en el formato que espera UserService
                    $tenantsConfig = $this->formatTenantsConfig($userData['organizaciones']);
                    $userDataForService = [
                        'name' => $userData['nombre'],
                        'last_name' => $userData['apellido'],
                        'email' => $userData['email'],
                        'document_type' => $userData['tipo_documento'],
                        'document_text' => $userData['numero_documento'],
                        'phone' => $userData['telefono'] ?? null,
                        // P1: fecha de nacimiento (campo de users). UserService::createUser
                        // ya lee 'birth_date' desde $data.
                        'birth_date' => $userData['birth_date'] ?? null,
                        'status' => $userData['estado'],
                        'tenants_config' => $tenantsConfig,
                    ];

                    // Crear usuario y despachar email con delay incremental (2s entre cada email)
                    $emailDelay = $created * 2; // 0s, 2s, 4s, 6s...
                    $userService->createUser($userDataForService, null, $sendWelcomeEmail, $emailDelay);
                    $created++;

                    // Registrar los tenants afectados por este usuario (para
                    // fijar initial_employee_count de forma idempotente al
                    // completar el batch, ver UserBatch::syncInitialEmployeeCounts)
                    foreach ($tenantsConfig as $cfg) {
                        if (!empty($cfg['tenant_id'])) {
                            $affectedTenantIds[] = (int) $cfg['tenant_id'];
                        }
                    }

                } catch (\Exception $e) {
                    Log::error('Error processing user in chunk (create/update)', [
                        'batch_uuid' => $this->batchUuid,
                        'chunk' => $this->chunkNumber,
                        'email' => $userData['email'],
                        'error' => $e->getMessage(),
                    ]);

                    $errors[] = [
                        'nombre' => $userData['nombre'] ?? 'N/A',
                        'apellido' => $userData['apellido'] ?? 'N/A',
                        'email' => $userData['email'],
                        'documento' => $userData['numero_documento'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            // 📊 Actualizar contadores en user_batches
            $this->updateBatchCounters($created, count($errors));

            // 🏢 Registrar tenants afectados (para el contador de empleados)
            if (!empty($affectedTenantIds)) {
                $this->mergeAffectedTenantIds($affectedTenantIds);
            }

            // 💾 Guardar errores detallados
            if (!empty($errors)) {
                $this->saveErrors($errors);
            }

            // ⚠️ Si TODOS los usuarios fallaron, es un problema sistemático → fallar el job
            $totalUsers = count($this->users);
            if (count($errors) === $totalUsers && $totalUsers > 0) {
                $errorMessage = "All users failed to process. First error: " . ($errors[0]['error'] ?? 'Unknown');
                Log::error("❌ [ProcessUserChunk] All users failed", [
                    'batch_uuid' => $this->batchUuid,
                    'chunk' => $this->chunkNumber,
                    'total_users' => $totalUsers,
                    'first_error' => $errors[0]['error'] ?? 'Unknown',
                ]);
                throw new \Exception($errorMessage);
            }

            Log::info("✅ [ProcessUserChunk] Chunk completed", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
                'created' => $created,
                'errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("❌ [ProcessUserChunk] Chunk failed", [
                'batch_uuid' => $this->batchUuid,
                'chunk' => $this->chunkNumber,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw para reintentos automáticos
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [ProcessUserChunk] Chunk failed permanently", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Resolver IDs de roles operativos a partir de sus nombres (lookup en BD,
     * sin hardcode). Ignora nombres inexistentes o el rol 'root'.
     *
     * @param array<string> $roleNames
     * @return array<int>
     */
    private function resolveRoleIdsByNames(array $roleNames): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn($name) => strtolower(trim((string) $name)),
            $roleNames
        ))));

        if (empty($names)) {
            return [];
        }

        return Role::whereIn('name', $names)
            ->where('name', '!=', 'root')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Formatear configuración de tenants.
     *
     * Por organización (RP1-C): los roles (org{n}_rol en el Excel) se
     * resuelven por nombre a role_ids. La carga masiva no tiene otra fuente
     * de roles —no existe una columna 'rol' de nivel de fila y 'root' no se
     * da de alta por esta vía—, así que UsersImport/UploadUserBatchDataRequest
     * exigen al menos un rol por organización. También se propagan
     * hire_date/vacation_balance_initial cuando existen.
     */
    private function formatTenantsConfig(?array $organizaciones): array
    {
        if (empty($organizaciones)) {
            return [];
        }

        $config = [];

        foreach ($organizaciones as $index => $org) {
            // Saltar organizaciones vacías o sin tenant_id
            if (empty($org) || !isset($org['tenant_id'])) {
                continue;
            }

            $tenantId = (int) $org['tenant_id'];
            $supervisors = [];

            if (isset($org['supervisor_id'])) {
                $supervisors = [(int) $org['supervisor_id']];
            }

            $item = [
                'tenant_id' => $tenantId,
                'is_primary' => $index === 0,
                'supervisors' => $supervisors,
            ];

            if (!empty($org['roles'])) {
                $roleIds = $this->resolveRoleIdsByNames(
                    is_array($org['roles']) ? $org['roles'] : explode(',', (string) $org['roles'])
                );
                if (!empty($roleIds)) {
                    $item['role_ids'] = $roleIds;
                }
            }

            if (!empty($org['hire_date'])) {
                $item['hire_date'] = $org['hire_date'];
            }

            if (isset($org['vacation_balance_initial']) && $org['vacation_balance_initial'] !== null && $org['vacation_balance_initial'] !== '') {
                $item['vacation_balance_initial'] = $org['vacation_balance_initial'];
            }

            if (!empty($org['department'])) {
                $item['department'] = $org['department'];
            }

            if (!empty($org['position'])) {
                $item['position'] = $org['position'];
            }

            $config[] = $item;
        }

        return $config;
    }

    /**
     * Buscar un usuario ya existente (match por documento y/o email; el
     * documento es la identidad más estable, el email puede haber cambiado).
     *
     * La búsqueda es GLOBAL, no limitada a las empresas del creador del
     * batch: 'users.email' tiene índice único a nivel de plataforma, así que
     * no detectar a una persona que existe en otra empresa no la haría "nueva"
     * —solo llevaría la fila al camino de creación para morir con un error de
     * clave duplicada—. Antes el match sí se limitaba por empresa (FIX B2.3),
     * pero eso era una defensa del update_existing: acotaba a quién se podía
     * PISAR. Al no actualizar a nadie, no hay nada que acotar, y detectar de
     * más solo produce un mensaje de error más claro.
     */
    private function findExistingUser(array $userData): ?User
    {
        $documentText = $userData['numero_documento'] ?? null;
        if (!empty($documentText)) {
            $user = User::where('document_text', $documentText)->first();
            if ($user) {
                return $user;
            }
        }

        $email = $userData['email'] ?? null;
        if (!empty($email)) {
            return User::where('email', $email)->first();
        }

        return null;
    }

    /**
     * FIX B2-r1: cuando el creador del batch NO es root, verifica que
     * administre (rol admin o admin_tenant) CADA tenant referenciado por
     * las organizaciones de esta fila ($organizaciones[]['tenant_id'], ya
     * resuelto desde ruc aguas arriba). Devuelve un mensaje de error claro
     * para la primera organización no autorizada, o null si la fila es
     * válida. root no se restringe (gestiona toda la plataforma). Si el
     * creador no puede resolverse, se trata como NO-root (fail-closed).
     *
     * @param array<int, array<string, mixed>> $organizaciones
     */
    private function tenantOwnershipError(array $organizaciones, ?User $creator, bool $creatorIsRoot): ?string
    {
        if ($creatorIsRoot || empty($organizaciones)) {
            return null;
        }

        foreach ($organizaciones as $org) {
            $tenantId = $org['tenant_id'] ?? null;
            if (!$tenantId) {
                continue;
            }

            $tenantId = (int) $tenantId;
            $manages = $creator
                && ($creator->hasRoleInTenant('admin', $tenantId) || $creator->hasRoleInTenant('admin_tenant', $tenantId));

            if (!$manages) {
                return "No tienes permisos para asignar usuarios a la empresa (tenant_id={$tenantId}).";
            }
        }

        return null;
    }

    /**
     * Registra los tenants afectados por este chunk (unión acumulada entre
     * chunks del mismo batch). Se usa al completar el batch para fijar
     * initial_employee_count de forma idempotente (ver
     * UserBatch::syncInitialEmployeeCounts).
     */
    protected function mergeAffectedTenantIds(array $tenantIds): void
    {
        try {
            $batch = UserBatch::where('uuid', $this->batchUuid)->first();
            $batch?->mergeAffectedTenantIds($tenantIds);
        } catch (\Exception $e) {
            Log::error('[ProcessUserChunk] Failed to merge affected tenant ids', [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualiza los contadores de user_batches después de procesar el chunk.
     *
     * 'updated_users' ya no se toca: la carga masiva no actualiza usuarios
     * existentes (la columna queda en 0 y no se muestra en ninguna pantalla).
     */
    protected function updateBatchCounters(int $created, int $failed): void
    {
        try {
            UserBatch::where('uuid', $this->batchUuid)->increment('created_users', $created);
            UserBatch::where('uuid', $this->batchUuid)->increment('failed_rows', $failed);
            UserBatch::where('uuid', $this->batchUuid)->increment('processed_rows', $created);
            UserBatch::where('uuid', $this->batchUuid)->update(['current_chunk' => $this->chunkNumber]);
        } catch (\Exception $e) {
            Log::error('[ProcessUserChunk] Failed to update batch counters', [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Guarda los errores detallados en error_summary
     */
    protected function saveErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        try {
            $batch = UserBatch::where('uuid', $this->batchUuid)->first();
            if (!$batch) {
                return;
            }

            // Obtener errores existentes o inicializar array vacío
            $existingErrors = $batch->error_summary ?? [];
            
            // Agregar nuevos errores con timestamp
            foreach ($errors as $error) {
                $existingErrors[] = array_merge($error, [
                    'chunk' => $this->chunkNumber,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }

            $batch->update(['error_summary' => $existingErrors]);
        } catch (\Exception $e) {
            Log::error('[ProcessUserChunk] Failed to save errors', [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
