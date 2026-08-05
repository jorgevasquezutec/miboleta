<?php

namespace App\Jobs;

use App\Models\Role;
use App\Models\User;
use App\Models\UserBatch;
use App\Services\BulkUserUploadService;
use App\Services\UserService;
use App\Support\DocumentNumber;
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
     * Observación 5 (historial de cargas masivas): tamaño de chunk usado por
     * UserBatchController::store()/uploadData() ($chunkSize = 50 en ambos,
     * ver :352 y :457) para trocear $validation['data']/$users antes de
     * despachar los jobs. Se necesita aquí -y no como parámetro del
     * constructor, para no tocar su firma ni los tests que ya lo instancian
     * con 3 argumentos- para reconstruir la fila GLOBAL (Excel) de cada error
     * a partir de $this->chunkNumber y la posición local dentro del chunk.
     */
    private const ROWS_PER_CHUNK = 50;

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

        // D5 (OBS-CLIENTE 2026-07): antes todo el chunk vivía en UNA sola
        // transacción con DB::commit() incondicional al final del foreach, y
        // recién DESPUÉS de ese commit se evaluaba si todas las filas habían
        // fallado para decidir si lanzar (con el DB::rollBack() del catch
        // exterior convertido en no-op: ya no había nada que revertir). El
        // síntoma reportado por el cliente ("dice error pero sí crea
        // usuarios") era exactamente eso: el job terminaba lanzando una
        // excepción -y el frontend lo mostraba como fallo- después de haber
        // comprometido en firme los usuarios válidos.
        //
        // Ahora cada fila vive en su propia transacción: una fila a medio
        // crear se revierte sola, sin arrastrar a las demás filas del chunk
        // (que ya están comprometidas de forma independiente). El job ya no
        // relanza por "todas las filas fallaron": eso es semántica normal de
        // negocio (ver UserBatch::onBatchFinished, que decide failed/partial/
        // completed por los contadores reales), no un error de
        // infraestructura. El job solo debe lanzar ante fallos genuinos de
        // infraestructura (excepciones no capturadas por el try por-fila).
        foreach ($this->users as $localIndex => $userData) {
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

                // Preparar datos en el formato que espera UserService. Se
                // pasa $creator para que resolveRoleIdsByNames sepa si
                // 'admin_tenant' es un rol permitido para esta fila
                // [OBS-CLIENTE 2026-08].
                $tenantsConfig = $this->formatTenantsConfig($userData['organizaciones'], $creator);
                $userDataForService = [
                    'name' => $userData['nombre'],
                    'last_name' => $userData['apellido'],
                    'email' => $userData['email'],
                    'document_type' => $userData['tipo_documento'],
                    // Garantía dura (Obs 4): repone el padding del documento
                    // aquí también, en el worker en cola, sin importar cómo
                    // llegó normalizado (o no) aguas arriba.
                    'document_text' => DocumentNumber::normalize($userData['tipo_documento'] ?? null, $userData['numero_documento'] ?? null),
                    'phone' => $userData['telefono'] ?? null,
                    // P1: fecha de nacimiento (campo de users). UserService::createUser
                    // ya lee 'birth_date' desde $data.
                    'birth_date' => $userData['birth_date'] ?? null,
                    'status' => $userData['estado'],
                    'tenants_config' => $tenantsConfig,
                ];

                // Crear usuario (en su propia transacción) y despachar email
                // con delay incremental (2s entre cada email). UserService::
                // createUser ya despacha el email DESPUÉS de su propio
                // commit interno (ver su doc), así que envolverlo aquí no
                // adelanta el envío respecto al commit de la fila.
                $emailDelay = $created * 2; // 0s, 2s, 4s, 6s...
                DB::transaction(fn () => $userService->createUser($userDataForService, null, $sendWelcomeEmail, $emailDelay));
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
                    // D5 (pulido): rol(es) por fila para que la columna Rol
                    // de BulkUploadErrors.tsx deje de mostrar "-".
                    'rol' => $this->extractRoleNames($userData['organizaciones'] ?? []),
                    'error' => $e->getMessage(),
                    // Observación 5: fila del Excel (1-based, +2 por
                    // encabezado, mismo criterio que UsersImport::parseRow y
                    // BulkUserUploadService::validateData) para trazar el
                    // error hasta la fila original. Se calcula por posición
                    // (chunk + índice local), no desde $userData['row_number']:
                    // ese campo lo pierde UploadUserBatchDataRequest::
                    // validated() en el camino de datos editados (no declara
                    // regla para 'users.*.row_number'), así que confiar en él
                    // dejaría este dato ausente justo en ese camino.
                    'row_number' => $this->globalRowNumber((int) $localIndex),
                ];
            }
        }

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

        Log::info("✅ [ProcessUserChunk] Chunk completed", [
            'batch_uuid' => $this->batchUuid,
            'chunk' => $this->chunkNumber,
            'created' => $created,
            'errors' => count($errors),
        ]);
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
     * Defensa en profundidad (hallazgo de Fase 5, OBS-CLIENTE 2026-07): la
     * lista blanca de roles asignables por carga masiva es
     * BulkUserUploadService::allowedOrgRolesFor($creator) (fuente única).
     * Antes este método resolvía CUALQUIER rol que existiera en la tabla
     * `roles` salvo 'root': si algo aguas arriba -payload armado a mano
     * contra el endpoint, o cualquier futura vía que no pase por
     * UploadUserBatchDataRequest/UsersImport- colaba 'admin_tenant' en
     * org{n}_rol, este job se lo asignaba sin más. Este es el único pipeline
     * que de verdad se ejecuta (ProcessBulkUserUpload se confirmó muerto y
     * se eliminó), así que es el punto que importa blindar en firme.
     *
     * [OBS-CLIENTE 2026-08]: 'admin_tenant' pasó de estar excluido para TODOS
     * a estar permitido SOLO cuando quien creó el batch ($creator) es root;
     * de ahí que este método reciba $creator en vez de comparar contra una
     * lista fija. Un rol no permitido se trata como error DE FILA (lanza, no
     * ignora en silencio): formatTenantsConfig() se llama dentro del try
     * por-fila de handle(), así que la excepción cae directo en
     * error_summary con el campo 'rol' poblado (ver extractRoleNames),
     * visible en BulkUploadErrors.tsx.
     *
     * @param array<string> $roleNames
     * @return array<int>
     */
    private function resolveRoleIdsByNames(array $roleNames, ?User $creator): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn($name) => strtolower(trim((string) $name)),
            $roleNames
        ))));

        if (empty($names)) {
            return [];
        }

        $allowedOrgRoles = BulkUserUploadService::allowedOrgRolesFor($creator);
        $disallowed = array_values(array_diff($names, $allowedOrgRoles));

        if (!empty($disallowed)) {
            throw new \Exception(
                'Rol(es) no permitido(s) en carga masiva: ' . implode(', ', $disallowed)
                . ' (permitidos: ' . implode(', ', $allowedOrgRoles) . ')'
            );
        }

        return Role::whereIn('name', $names)
            ->where('name', '!=', 'root')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * Observación 5: fila GLOBAL (Excel, 1-based, +2 por encabezado) de una
     * fila procesada por este chunk, a partir de su posición LOCAL dentro del
     * array $this->users. $this->chunkNumber es 1-based (ver
     * UserBatchController::store()/uploadData(), que despachan
     * `new ProcessUserChunk($batch->uuid, $chunk, $index + 1)`), así que el
     * offset del chunk es `(chunkNumber - 1) * ROWS_PER_CHUNK`.
     */
    private function globalRowNumber(int $localIndex): int
    {
        return ($this->chunkNumber - 1) * self::ROWS_PER_CHUNK + $localIndex + 2;
    }

    /**
     * D5 (pulido): nombres de rol legibles de TODAS las organizaciones de la
     * fila, para la columna Rol de BulkUploadErrors.tsx (antes siempre "-"
     * porque saveErrors() nunca guardaba este campo). A diferencia de
     * resolveRoleIdsByNames(), no resuelve contra BD ni filtra 'root': es
     * solo texto de diagnóstico para un error, no se usa para autorizar nada.
     *
     * @param array<int, array<string, mixed>> $organizaciones
     */
    private function extractRoleNames(array $organizaciones): string
    {
        $names = [];

        foreach ($organizaciones as $org) {
            $roles = is_array($org) ? ($org['roles'] ?? null) : null;

            if (empty($roles)) {
                continue;
            }

            $roles = is_array($roles) ? $roles : explode(',', (string) $roles);

            foreach ($roles as $role) {
                $role = trim((string) $role);
                if ($role !== '') {
                    $names[] = $role;
                }
            }
        }

        return implode(', ', array_values(array_unique($names)));
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
     *
     * @param ?User $creator Creador del batch (ver handle()); se reenvía a
     *   resolveRoleIdsByNames para decidir si 'admin_tenant' es válido.
     */
    private function formatTenantsConfig(?array $organizaciones, ?User $creator): array
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
                    is_array($org['roles']) ? $org['roles'] : explode(',', (string) $org['roles']),
                    $creator
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
        // Normalizado (con padding, Obs 4) para que "1234567" y "01234567"
        // matcheen al mismo documento. También se busca la variante SIN
        // padding: hay documentos ya guardados en BD de antes de este fix.
        $documentText = DocumentNumber::normalize($userData['tipo_documento'] ?? null, $userData['numero_documento'] ?? null);
        if (!empty($documentText)) {
            $candidates = [$documentText];
            $stripped = ltrim($documentText, '0');
            if ($stripped !== '' && $stripped !== $documentText) {
                $candidates[] = $stripped;
            }

            $user = User::whereIn('document_text', $candidates)->first();
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
     *
     * 'processed_rows' cuenta las filas PROCESADAS (creadas + fallidas), no
     * solo las creadas: antes se incrementaba solo por $created, así que un
     * batch con filas inválidas reportaba menos filas procesadas de las que
     * realmente había recorrido. No afecta 'progress_percentage', que se
     * calcula aparte desde el progreso por chunks de Bus::batch().
     */
    protected function updateBatchCounters(int $created, int $failed): void
    {
        try {
            UserBatch::where('uuid', $this->batchUuid)->increment('created_users', $created);
            UserBatch::where('uuid', $this->batchUuid)->increment('failed_rows', $failed);
            UserBatch::where('uuid', $this->batchUuid)->increment('processed_rows', $created + $failed);
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
