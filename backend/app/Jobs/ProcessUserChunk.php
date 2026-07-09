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
        // (send_welcome_emails / update_existing). Ver
        // UserBatchController@store / @uploadData, que las guarda al crear
        // el batch.
        $batch = UserBatch::where('uuid', $this->batchUuid)->first();
        $processingOptions = $batch?->processing_options ?? [];
        $sendWelcomeEmail = (bool) ($processingOptions['send_welcome_emails'] ?? true);
        $updateExisting = (bool) ($processingOptions['update_existing'] ?? false);

        // 🔐 FIX B2.3: quién creó el batch (UserBatch::created_by_user_id,
        // guardado por UserBatchController@store/@uploadData). Se usa para
        // (a) limitar a qué usuarios existentes puede "pisar" un
        // update_existing (findExistingUser) y (b) evitar que ese
        // update_existing cambie el email o eleve el rol a root/admin_tenant
        // cuando quien subió el batch no es root. Si no se puede resolver el
        // creador, se trata como NO-root (restrictivo por defecto).
        $creator = $batch?->createdBy;
        $creatorIsRoot = $creator?->isRoot() ?? false;

        Log::info("🔄 [ProcessUserChunk] Processing chunk", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'users_count' => count($this->users),
            'send_welcome_email' => $sendWelcomeEmail,
            'update_existing' => $updateExisting,
        ]);

        $created = 0;
        $updated = 0;
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
                    // Empresa B (ajena) y el usuario terminaba creado/
                    // adjunto a esa empresa ajena. Se aplica ANTES de
                    // decidir create/update para cubrir ambos caminos (el
                    // update_existing ya tenía un scoping parcial vía
                    // findExistingUser/FIX B2.3, pero el path de creación no
                    // tenía ninguno).
                    $ownershipError = $this->tenantOwnershipError(
                        $userData['organizaciones'] ?? [],
                        $creator,
                        $creatorIsRoot
                    );
                    if ($ownershipError !== null) {
                        throw new \Exception($ownershipError);
                    }

                    // Verificar si el usuario ya existe (match por documento
                    // y/o email; el documento es más confiable como
                    // identidad, el email puede haber cambiado). FIX B2.3:
                    // el match se limita a los tenants del creador del
                    // batch cuando este no es root (ver findExistingUser).
                    $existingUser = $this->findExistingUser($userData, $creator, $creatorIsRoot);

                    if ($existingUser) {
                        if (!$updateExisting) {
                            // Comportamiento histórico: omitir usuarios que ya existen.
                            continue;
                        }

                        $tenantsConfig = $this->formatTenantsConfig($userData['organizaciones']);
                        $updateData = [
                            'name' => $userData['nombre'],
                            'last_name' => $userData['apellido'],
                            'email' => $userData['email'],
                            'document_type' => $userData['tipo_documento'],
                            'document_text' => $userData['numero_documento'],
                            'phone' => $userData['telefono'] ?? null,
                            'status' => $userData['estado'],
                            'role_id' => $this->getRoleId($userData['rol']),
                            'tenants_config' => $tenantsConfig,
                        ];

                        // P1: solo tocar birth_date si esta fila trae un valor
                        // explícito; no pisar con null una fecha de nacimiento
                        // ya guardada cuando la fila reprocesada no la trae.
                        if (!empty($userData['birth_date'])) {
                            $updateData['birth_date'] = $userData['birth_date'];
                        }

                        // 🔐 FIX B2.3: defensa adicional (además del scoping
                        // por tenant de findExistingUser): cuando quien subió
                        // el batch no es root, update_existing tampoco puede
                        // cambiar el email del usuario objetivo ni elevar su
                        // rol a root/admin_tenant, ni siquiera si el objetivo
                        // cae dentro del tenant del creador. Esto cierra la
                        // vía de escalada/account-takeover por la columna
                        // 'rol' (role_id de respaldo) y por
                        // organizaciones[].roles.
                        if (!$creatorIsRoot) {
                            unset($updateData['email']);
                            $updateData['role_id'] = $this->stripElevatedRoleId($updateData['role_id']);
                            $updateData['tenants_config'] = $this->stripElevatedRoleIds($tenantsConfig);
                        }

                        // preserveTenantFieldsWhenMissing = true: no borrar
                        // hire_date/vacation_balance_initial/department/position
                        // ya guardados si esta fila reprocesada no los trae
                        // explícitos (ver UserService::updateUser). La
                        // contraseña NUNCA se toca aquí (no se incluye
                        // 'password' en $updateData).
                        //
                        // FIX I3(b): mergeTenants = true. A diferencia de la
                        // edición individual (UserController@update, que sí
                        // debe respetar el sync explícito del formulario y
                        // por tanto puede desenganchar tenants no listados),
                        // una fila reprocesada de carga masiva típicamente
                        // solo trae LAS organizaciones que le conciernen a
                        // quien subió el archivo; no se debe interpretar la
                        // ausencia de otras organizaciones del usuario como
                        // "quítaselas". Ver UserService::assignTenantsWithConfig.
                        $userService->updateUser($existingUser, $updateData, true, true);
                        $updated++;

                        Log::info('🔁 [ProcessUserChunk] Usuario existente actualizado', [
                            'batch_uuid' => $this->batchUuid,
                            'chunk' => $this->chunkNumber,
                            'user_id' => $existingUser->id,
                            'email' => $userData['email'],
                            'documento' => $userData['numero_documento'] ?? null,
                        ]);

                        foreach ($tenantsConfig as $cfg) {
                            if (!empty($cfg['tenant_id'])) {
                                $affectedTenantIds[] = (int) $cfg['tenant_id'];
                            }
                        }

                        continue;
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
                        'role_id' => $this->getRoleId($userData['rol']),
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
                        'rol' => $userData['rol'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            // 📊 Actualizar contadores en user_batches
            $this->updateBatchCounters($created, $updated, count($errors));

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
                'updated' => $updated,
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
     * Obtener ID del rol por nombre (columna 'rol' de nivel de fila).
     *
     * FIX B2.2: 'root' se excluye a propósito (defensa en profundidad) aun
     * cuando ya no debería llegar hasta aquí, porque la validación de
     * entrada (UploadUserBatchDataRequest/UsersImport) ya no lo admite como
     * valor de fila; si de algún modo llegara, cae al default 'client' en
     * vez de escalar a root. Se resuelve por lookup en BD (sin IDs
     * hardcodeados) para soportar correctamente 'admin_tenant' y
     * 'aprobador', que ahora también son valores válidos de esta columna.
     *
     * @deprecated Fallback de compat para el rol global de la fila. Para
     * roles operativos por organización usar resolveRoleIdsByNames().
     */
    private function getRoleId(string $roleName): int
    {
        $roleName = strtolower(trim($roleName));
        $allowedNames = ['client', 'admin', 'admin_tenant', 'aprobador'];

        if (in_array($roleName, $allowedNames, true)) {
            $roleId = Role::where('name', $roleName)->value('id');
            if ($roleId) {
                return (int) $roleId;
            }
        }

        return (int) (Role::where('name', 'client')->value('id') ?? 3); // Default: client
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
     * Formatear configuración de tenants
     * Para rol root: puede no tener organizaciones
     * Para rol client/admin: debe tener organizaciones con tenant_id
     *
     * Por organización (RP1-C): si vienen roles explícitos (org{n}_rol en el
     * Excel), se resuelven por nombre a role_ids; si no, se omite la clave
     * para que UserService use el rol global de la fila como fallback (ver
     * UserService::assignTenantsWithConfig). También se propagan
     * hire_date/vacation_balance_initial cuando existen.
     */
    private function formatTenantsConfig(?array $organizaciones): array
    {
        // Si no hay organizaciones, retornar array vacío (válido para root)
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
     * Buscar un usuario ya existente para decidir si esta fila debe crear o
     * actualizar (ver processing_options.update_existing). Se prioriza el
     * documento (identidad más estable) y se recurre al email como
     * respaldo, porque una fila reprocesada podría traer un email corregido
     * para el mismo documento.
     *
     * FIX B2.3: el match se restringe vía scopedExistingUserQuery() a los
     * tenants del creador del batch cuando este no es root (ver doc ahí).
     */
    private function findExistingUser(array $userData, ?User $creator, bool $creatorIsRoot): ?User
    {
        $documentText = $userData['numero_documento'] ?? null;
        if (!empty($documentText)) {
            $user = $this->scopedExistingUserQuery($creator, $creatorIsRoot)
                ->where('document_text', $documentText)
                ->first();
            if ($user) {
                return $user;
            }
        }

        $email = $userData['email'] ?? null;
        if (!empty($email)) {
            return $this->scopedExistingUserQuery($creator, $creatorIsRoot)
                ->where('email', $email)
                ->first();
        }

        return null;
    }

    /**
     * FIX B2.3: base query para localizar un usuario existente al procesar
     * update_existing. Cuando el creador del batch NO es root, el match se
     * restringe a usuarios que comparten al menos un tenant con el
     * creador; sin este scoping, cualquier admin/admin_tenant podía, con
     * update_existing=true, hacer match por documento/email de un usuario
     * de OTRA empresa (ajena a las suyas) y pisar su email/rol -> account
     * takeover fuera de su alcance. root no se limita (gestiona toda la
     * plataforma). Si el creador no puede resolverse o no tiene tenants
     * propios, no se matchea a NADIE (falla cerrado: la fila termina
     * creando un usuario nuevo en vez de arriesgarse a pisar uno ajeno).
     */
    private function scopedExistingUserQuery(?User $creator, bool $creatorIsRoot)
    {
        $query = User::query();

        if ($creatorIsRoot) {
            return $query;
        }

        $tenantIds = $creator?->tenants()->pluck('tenants.id') ?? collect();
        if ($tenantIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('tenants', function ($q) use ($tenantIds) {
            $q->whereIn('tenants.id', $tenantIds);
        });
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
     * FIX B2.3: si $roleId corresponde a 'root' o 'admin_tenant', lo
     * degrada al id de 'client'. Se usa para el role_id de respaldo
     * (columna 'rol') en update_existing cuando el creador del batch no es
     * root, para no permitir elevar a un usuario existente a un rol que el
     * actor no podría asignar por la vía normal (StoreUserRequest/
     * UpdateUserRequest).
     */
    private function stripElevatedRoleId(?int $roleId): ?int
    {
        if (!$roleId) {
            return $roleId;
        }

        $isElevated = Role::whereKey($roleId)->whereIn('name', ['root', 'admin_tenant'])->exists();
        if (!$isElevated) {
            return $roleId;
        }

        return $this->getRoleId('client');
    }

    /**
     * FIX B2.3: elimina, de cada item de $tenantsConfig, los role_ids que
     * resuelvan a 'root' o 'admin_tenant' (organizaciones[].roles del
     * Excel/grid), para update_existing cuando el creador del batch no es
     * root.
     *
     * @param array $tenantsConfig
     * @return array
     */
    private function stripElevatedRoleIds(array $tenantsConfig): array
    {
        $elevatedRoleIds = Role::whereIn('name', ['root', 'admin_tenant'])->pluck('id')->all();
        if (empty($elevatedRoleIds)) {
            return $tenantsConfig;
        }

        foreach ($tenantsConfig as &$item) {
            if (!empty($item['role_ids'])) {
                $item['role_ids'] = array_values(array_diff($item['role_ids'], $elevatedRoleIds));
            }
        }
        unset($item);

        return $tenantsConfig;
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
     * Actualiza los contadores de user_batches después de procesar el chunk
     */
    protected function updateBatchCounters(int $created, int $updated, int $failed): void
    {
        try {
            UserBatch::where('uuid', $this->batchUuid)->increment('created_users', $created);
            UserBatch::where('uuid', $this->batchUuid)->increment('updated_users', $updated);
            UserBatch::where('uuid', $this->batchUuid)->increment('failed_rows', $failed);
            UserBatch::where('uuid', $this->batchUuid)->increment('processed_rows', $created + $updated);
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
