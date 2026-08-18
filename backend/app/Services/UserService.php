<?php

namespace App\Services;

use App\Exceptions\UserCreationException;
use App\Mail\EmailChangedNotificationMail;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Support\TenantAccessCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * ID convencional del rol 'root' (ver RoleSeeder: es el primer rol
     * insertado). Root es global: no tiene empresas ni roles por empresa.
     */
    protected const ROOT_ROLE_ID = 1;

    /**
     * Roles operativos que $actor puede ASIGNAR a otro usuario al crear/
     * editar sus roles, dentro de una misma empresa: rol del actor => roles
     * que puede asignar. Root no aparece como clave: se resuelve aparte
     * (puede asignar cualquier rol operativo) porque no tiene rol por
     * empresa. 'client' y 'aprobador' tampoco aparecen: no asignan roles a
     * nadie.
     *
     * Fuente única de verdad para assignableRoleNamesFor() (usado por
     * StoreUserRequest y UpdateUserRequest; antes duplicado literal en
     * ambos).
     *
     * OBS-CLIENTE 2026-08 (Observación 1): "asignar" (esta constante) y
     * "administrar" (MANAGEABLE_ROLES, abajo) ya NO son la misma jerarquía.
     * Un admin_tenant sigue pudiendo asignar el rol admin al crear/editar
     * roles de un usuario, pero ya no puede ADMINISTRAR (editar, resetear
     * contraseña, eliminar) una cuenta admin existente — ver
     * MANAGEABLE_ROLES y canManageUser().
     */
    public const ASSIGNABLE_ROLES = [
        'admin_tenant' => ['admin', 'aprobador', 'client'],
        'admin' => ['aprobador', 'client'],
    ];

    /**
     * Jerarquía RBAC de "quién puede ADMINISTRAR a quién" (editar, resetear
     * contraseña, eliminar) DENTRO de una misma empresa: rol del actor =>
     * roles operativos que puede administrar. Root no aparece como clave: se
     * resuelve aparte (puede administrar cualquier rol operativo) porque no
     * tiene rol por empresa. 'client' y 'aprobador' tampoco aparecen: no
     * administran a nadie.
     *
     * Fuente única de verdad para canManageUser() (ver abajo — Decisión C1
     * del plan de observaciones del cliente 2026-07, endurecida por
     * Observación 1 2026-08: solo root administra cuentas admin; admin_tenant
     * conserva la potestad de ASIGNAR el rol admin, ver ASSIGNABLE_ROLES
     * arriba, pero no la de administrar la cuenta ya creada).
     */
    public const MANAGEABLE_ROLES = [
        'admin_tenant' => ['aprobador', 'client'],
        'admin' => ['aprobador', 'client'],
    ];

    /**
     * Roles operativos que $actor puede asignar a otro usuario dentro de la
     * empresa $tenantId, según el rol que el propio $actor tiene EN ESA
     * empresa (jerarquía RBAC: root > admin_tenant > admin > aprobador/
     * client). Root no tiene rol por empresa (es global), así que se
     * resuelve aparte.
     *
     * Fuente única para StoreUserRequest::assignableRoleNamesFor() y
     * UpdateUserRequest::assignableRoleNamesFor() (antes duplicado literal
     * en ambos archivos).
     *
     * @return list<string> Nombres de rol permitidos (vacío si no puede asignar ninguno).
     */
    public static function assignableRoleNamesFor(User $actor, int $tenantId): array
    {
        if ($actor->isRoot()) {
            return ['admin_tenant', 'admin', 'aprobador', 'client'];
        }

        foreach (self::ASSIGNABLE_ROLES as $actorRole => $allowedRoles) {
            if ($actor->hasRoleInTenant($actorRole, $tenantId)) {
                return $allowedRoles;
            }
        }

        return [];
    }

    /**
     * Create a new user with all related data.
     *
     * @param array $data Validated user data
     * @param User|null $creator The user creating this new user (optional, defaults to auth user)
     * @param bool $sendWelcomeEmail Whether to send welcome email
     * @param int|null $emailDelay Delay in seconds before sending email (for bulk uploads to avoid rate limiting)
     * @return User
     * @throws UserCreationException
     */
    public function createUser(array $data, ?User $creator = null, bool $sendWelcomeEmail = true, ?int $emailDelay = null): User
    {
        DB::beginTransaction();

        try {
            $creator = $creator ?? Auth::user();

            // Generate temporary password
            $temporaryPassword = $this->generateTemporaryPassword();

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'document_type' => $data['document_type'] ?? null,
                'document_text' => $data['document_text'] ?? null,
                'phone' => $data['phone'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'status' => $data['status'] ?? 'active',
                'must_change_password' => true,
            ]);

            $roleId = isset($data['role_id']) ? (int) $data['role_id'] : null;

            if ($this->isRootRoleId($roleId)) {
                // Root: rol global, sin empresas ni roles por empresa.
                $this->assignGlobalRole($user, $roleId, $creator);
            } else {
                // Usuario operativo: los roles se asignan por empresa
                // (user_tenant_roles), no de forma global.
                if (isset($data['tenants_config']) && is_array($data['tenants_config'])) {
                    $this->assignTenantsWithConfig($user, $data['tenants_config'], $roleId);
                } elseif (isset($data['tenant_id'])) {
                    // Fallback legacy: tenant simple sin desglose de tenants_config.
                    $this->assignTenants($user, [$data['tenant_id']], true);

                    if ($roleId) {
                        $this->assignRoles($user, (int) $data['tenant_id'], [$roleId]);
                    }
                }

                // Mantener el respaldo global (user_roles) para no romper el
                // código existente que aún resuelve el rol sin contexto de
                // empresa (ver syncGlobalRoleFallback).
                $this->syncGlobalRoleFallback($user, $creator);
            }

            // Assign orphan documents if document_text is provided
            $defaultTenantId = $data['tenants_config'][0]['tenant_id'] ?? $data['tenant_id'] ?? null;
            if (!empty($data['document_text']) && $defaultTenantId) {
                $this->assignOrphanDocuments($user, $data['document_text'], $defaultTenantId);
            }

            DB::commit();

            // Send welcome email DESPUÉS del commit (evitar enviar email si falla la transacción)
            if ($sendWelcomeEmail) {
                if ($emailDelay !== null && $emailDelay > 0) {
                    // Despachar job con delay para evitar rate limiting
                    \App\Jobs\SendWelcomeEmailJob::dispatch($user->id, $temporaryPassword)
                        ->delay(now()->addSeconds($emailDelay));
                } else {
                    // Enviar inmediatamente (creación individual)
                    $this->sendWelcomeEmail($user, $temporaryPassword);
                }
            }

            return $user->load(['roles', 'tenants', 'tenantRoles.role']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[UserService] Failed to create user', [
                'error' => $e->getMessage(),
                'data' => array_diff_key($data, ['password' => '']),
            ]);
            throw new UserCreationException("Error al crear el usuario: " . $e->getMessage());
        }
    }

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * NOTA: los dos flags de abajo se introdujeron para la actualización de
     * usuarios existentes de la carga masiva, que se eliminó (esa vía ahora
     * solo da de alta usuarios nuevos). Hoy ningún llamador los pone en true:
     * la edición individual (UserController@update) usa los defaults. Se
     * conservan porque describen un comportamiento coherente y su borrado
     * tocaría el camino de edición individual sin necesidad.
     *
     * @param bool $preserveTenantFieldsWhenMissing Cuando es true, los campos
     *   "opcionales por empresa" (hire_date, vacation_balance_initial,
     *   department, position, supervisor_id) que NO vengan explícitos en
     *   $data['tenants_config'][n] conservan el valor ya guardado en el
     *   pivote en vez de limpiarse a null (ver assignTenantsWithConfig). El
     *   formulario de edición individual usa el comportamiento histórico
     *   (false): un campo vacío en el formulario SÍ limpia el valor, porque
     *   ahí "vacío" es una acción explícita del usuario.
     * @param bool $mergeTenants FIX I3(b): cuando es true, la sincronización
     *   de tenants_config usa MERGE (syncWithoutDetaching) en vez de sync
     *   (que desengancha cualquier tenant no listado en $data). La edición
     *   individual usa el comportamiento histórico (false): el formulario
     *   reenvía la lista COMPLETA de tenants del usuario, así que un sync
     *   explícito (con detach) es el comportamiento correcto ahí.
     * @return User
     */
    public function updateUser(User $user, array $data, bool $preserveTenantFieldsWhenMissing = false, bool $mergeTenants = false): User
    {
        DB::beginTransaction();

        try {
            $oldDocumentText = $user->document_text;
            // NOTA: tras el modelo híbrido, roles() (user_roles) solo contiene
            // 'root' más el respaldo global sincronizado por
            // syncGlobalRoleFallback para usuarios operativos. $oldRoleId se
            // usa para detectar cambios de rol y transiciones hacia/desde root.
            $oldRoleId = $user->roles()->first()?->id;
            $wasRoot = $user->isRoot();
            $userAuth = Auth::user();

            // Detect email change before update
            $emailChanged = isset($data['email']) && $data['email'] !== $user->email;
            $oldEmail = $user->email;

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $newRoleId = isset($data['role_id']) ? (int) $data['role_id'] : null;
            $roleChanged = $newRoleId !== null && $newRoleId !== $oldRoleId;
            $becomingRoot = $roleChanged && $this->isRootRoleId($newRoleId);
            $tenantFieldsProvided = isset($data['tenants_config']) || isset($data['tenant_ids']) || isset($data['tenant_id']);

            // Handle role change
            if ($roleChanged) {
                if ($becomingRoot) {
                    // Si el nuevo rol es root, remover todos los tenants y roles por empresa
                    $user->tenants()->detach();
                    UserTenantRole::where('user_id', $user->id)->delete();
                    TenantAccessCache::forget($user->id);
                    unset($data['tenants_config'], $data['tenant_id'], $data['tenant_ids']);
                    $tenantFieldsProvided = false;

                    $this->assignGlobalRole($user, $newRoleId, $userAuth);
                } else {
                    // Si cambió de root a otro rol y no viene config, asegurar tenant
                    if ($wasRoot && isset($data['tenant_id']) && !isset($data['tenants_config'])) {
                        $this->assignTenants($user, [$data['tenant_id']], true);
                    }
                }
            }

            // Handle tenant/role assignment for non-root users (solo si hay
            // algo que aplicar: cambio de rol o datos de tenant en el request)
            if (!$becomingRoot && ($roleChanged || $tenantFieldsProvided)) {
                if (isset($data['tenants_config']) && is_array($data['tenants_config'])) {
                    $this->assignTenantsWithConfig($user, $data['tenants_config'], $newRoleId, $preserveTenantFieldsWhenMissing, $mergeTenants);
                } elseif (isset($data['tenant_ids']) && is_array($data['tenant_ids'])) {
                    // Legacy/Simple assignment without supervisors update (or keeping existing)
                    // But sync deletes existing pivot data. We should be careful.
                    // For now, simple behavior:
                    $primaryTenantId = $data['primary_tenant_id'] ?? $data['tenant_ids'][0];
                    $pivotData = [];
                    foreach ($data['tenant_ids'] as $tenantId) {
                        $pivotData[$tenantId] = ['is_primary' => $tenantId == $primaryTenantId];
                    }
                    // FIX I3(a): limpiar user_tenant_roles huérfanos de los
                    // tenants que el sync haya desenganchado (ver doc de
                    // syncTenantsAndCleanOrphanRoles).
                    $this->syncTenantsAndCleanOrphanRoles($user, $pivotData, $mergeTenants);

                    if ($newRoleId) {
                        foreach ($data['tenant_ids'] as $tenantId) {
                            $this->assignRoles($user, (int) $tenantId, [$newRoleId]);
                        }
                    }
                } elseif (isset($data['tenant_id'])) {
                    // FIX I3(a): idem, ver comentario arriba.
                    $this->syncTenantsAndCleanOrphanRoles($user, [
                        $data['tenant_id'] => ['is_primary' => true]
                    ], $mergeTenants);

                    if ($newRoleId) {
                        $this->assignRoles($user, (int) $data['tenant_id'], [$newRoleId]);
                    }
                } elseif ($roleChanged) {
                    // Cambio de rol global "legacy" sin especificar tenant
                    // explícito: aplicar el nuevo rol a todas las empresas ya
                    // asignadas al usuario.
                    foreach ($user->tenants()->pluck('tenants.id') as $tenantId) {
                        $this->assignRoles($user, (int) $tenantId, [$newRoleId]);
                    }
                }

                // Mantener sincronizado el respaldo global de roles tras
                // cualquier cambio de roles/empresas (ver
                // syncGlobalRoleFallback). Es una operación idempotente.
                $this->syncGlobalRoleFallback($user, $userAuth);
            }

            // Remove tenant-related fields from user update
            unset(
                $data['role_id'],
                $data['tenant_id'],
                $data['tenant_ids'],
                $data['primary_tenant_id'],
                $data['tenants_config']
            );

            $user->update($data);

            // If document_text changed, assign orphan documents
            if (isset($data['document_text']) && $data['document_text'] !== $oldDocumentText) {
                $primaryTenant = $user->tenants()->wherePivot('is_primary', true)->first();
                if ($primaryTenant) {
                    $this->assignOrphanDocuments($user, $data['document_text'], $primaryTenant->id);
                }
            }

            // If email changed, force password change
            if ($emailChanged) {
                $user->update(['must_change_password' => true]);
            }

            DB::commit();

            // Send email change notification AFTER commit (avoid sending if transaction fails)
            // NOTA (SMTP por empresa): fuera del alcance actual del enrutado
            // por tenant (ver TenantMailerService); se envía con el mailer
            // por defecto de la plataforma. El usuario puede tener varias
            // empresas, así que no hay un único tenant obvio para resolver.
            if ($emailChanged) {
                $newEmail = $data['email'];
                $this->auditService->logEmailChanged($user->id, $oldEmail, $newEmail);
                try {
                    $notification = new EmailChangedNotificationMail($user, $oldEmail, $newEmail);
                    Mail::to($oldEmail)->send($notification);
                    Mail::to($newEmail)->send($notification);
                } catch (\Exception $e) {
                    Log::warning('[UserService] Failed to send email change notification', [
                        'user_id' => $user->id,
                        'old_email' => $oldEmail,
                        'new_email' => $newEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $user->load(['roles', 'tenants', 'tenantRoles.role']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[UserService] Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Determine if a role id corresponds to the global 'root' role.
     */
    protected function isRootRoleId(?int $roleId): bool
    {
        if (!$roleId) {
            return false;
        }

        return Role::where('id', $roleId)->where('name', 'root')->exists();
    }

    /**
     * Assign the global 'root' role to a user (user_roles, sin empresas).
     *
     * @param User $user
     * @param int $roleId
     * @param User|null $grantedBy
     * @return void
     */
    protected function assignGlobalRole(User $user, int $roleId, ?User $grantedBy): void
    {
        $oldRoleIds = $user->roles()->pluck('roles.id')->all();

        $user->roles()->sync([
            $roleId => [
                'granted_by' => $grantedBy?->id,
                'granted_at' => now(),
            ],
        ]);

        // Solo se audita en contexto interactivo (hay usuario autenticado). En
        // cargas masivas (jobs, sin Auth) queda cubierto por user_batch.completed.
        if (Auth::check()) {
            $this->auditService->logRoleAssigned($user->id, null, [$roleId], $oldRoleIds);
        }
    }

    /**
     * Assign operational roles to a user within a specific tenant
     * (user_tenant_roles). Reemplaza cualquier rol previo del usuario en esa
     * empresa por el conjunto indicado (comportamiento tipo "sync" por
     * empresa).
     *
     * @param User $user
     * @param int $tenantId
     * @param array<int> $roleIds Roles operativos (admin_tenant, admin, client, aprobador)
     * @return void
     */
    protected function assignRoles(User $user, int $tenantId, array $roleIds): void
    {
        $oldRoleIds = UserTenantRole::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->pluck('role_id')
            ->all();

        UserTenantRole::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->delete();

        $now = now();
        $rows = [];
        foreach (array_unique($roleIds) as $roleId) {
            $rows[] = [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            UserTenantRole::insert($rows);
        }

        // Solo se audita en contexto interactivo (hay usuario autenticado). En
        // cargas masivas (jobs, sin Auth) queda cubierto por user_batch.completed.
        if (Auth::check()) {
            $this->auditService->logRoleAssigned($user->id, $tenantId, $roleIds, $oldRoleIds);
        }
    }

    /**
     * Mantiene sincronizado el rol "global" de respaldo del usuario (tabla
     * user_roles) con la unión de los roles operativos que tiene asignados
     * en todas sus empresas (user_tenant_roles).
     *
     * Esto es un resto de transición: la fuente de verdad para roles operativos
     * es user_tenant_roles, y la AUTORIZACIÓN ya no depende de este respaldo
     * (las abilities de config/access_matrix.php resuelven el rol por empresa
     * vía User::roleForTenant(), sin fallback global). Lo que todavía lee
     * user_roles es getCurrentRole() sin empresa, hoy limitado a usos de
     * DISPLAY (UserResource, ProfileService, logs, exportes) más el filtrado
     * por fila de DocumentService::getDocuments() (ver Riesgo #2 del plan).
     *
     * TODO(RP1-D): eliminar este respaldo cuando el listado de documentos
     * filtre por rol de la empresa de cada fila y los usos de display resuelvan
     * el rol vía tenant activo. Nótese que, si un usuario tiene roles distintos
     * en distintas empresas, este respaldo expone la UNIÓN de todos ellos (no
     * solo el de la empresa "activa"), lo cual es más permisivo de lo ideal.
     *
     * @param User $user
     * @param User|null $grantedBy
     * @return void
     */
    protected function syncGlobalRoleFallback(User $user, ?User $grantedBy = null): void
    {
        if ($user->isRoot()) {
            return; // root se gestiona aparte (assignGlobalRole), no se toca aquí
        }

        $grantedBy = $grantedBy ?? Auth::user() ?? $user;

        $roleIds = UserTenantRole::where('user_id', $user->id)
            ->distinct()
            ->pluck('role_id');

        // Guard anti-erosión: si el usuario NO tiene roles por empresa pero
        // SÍ sigue teniendo empresas asignadas, el vacío es un estado
        // transitorio/inconsistente (p.ej. usuario aún no backfilleado), no
        // una revocación intencional. Ejecutar sync([]) aquí borraría su rol
        // global en user_roles y lo dejaría sin rol en los usos que aún lo leen
        // (display y el filtrado por fila de documentos). Preservamos
        // user_roles tal cual. Si el usuario ya no tiene NINGUNA empresa,
        // caemos al sync([]) de abajo (revocación legítima del fallback).
        if ($roleIds->isEmpty() && $user->tenants()->exists()) {
            Log::warning('[UserService] syncGlobalRoleFallback: user_tenant_roles vacío con tenants asignados; se omite sync([]) para no erosionar user_roles', [
                'user_id' => $user->id,
            ]);
            return;
        }

        $pivotData = [];
        foreach ($roleIds as $roleId) {
            $pivotData[$roleId] = [
                'granted_by' => $grantedBy->id,
                'granted_at' => now(),
            ];
        }

        $user->roles()->sync($pivotData);
    }

    /**
     * Assign tenants to a user (simple version without supervisor).
     *
     * @param User $user
     * @param array $tenantIds
     * @param bool $setPrimaryFirst
     * @return void
     */
    protected function assignTenants(User $user, array $tenantIds, bool $setPrimaryFirst = true): void
    {
        $pivotData = [];
        foreach ($tenantIds as $index => $tenantId) {
            $pivotData[$tenantId] = [
                'is_primary' => $setPrimaryFirst && $index === 0,
                'supervisor_id' => null,
            ];
        }
        $user->tenants()->sync($pivotData);

        TenantAccessCache::forget($user->id);
    }

    /**
     * Assign tenants with supervisor/role configuration.
     *
     * @param User $user
     * @param array $config Array de:
     *   [
     *     'tenant_id' => int,
     *     'role_ids' => int[],               // roles operativos para esa empresa (user_tenant_roles); opcional
     *     'supervisor_id' => ?int,           // o 'supervisors' => int[] (compat carga masiva, se usa el primero)
     *     'is_primary' => bool,
     *     'hire_date' => ?string,
     *     'vacation_balance_initial' => ?float,
     *     'department' => ?string,
     *     'position' => ?string,
     *   ]
     * @param int|null $fallbackRoleId Rol a aplicar en las empresas cuyo item no traiga 'role_ids'.
     * @param bool $preserveWhenMissing Ver doc de updateUser(). false (default) = comportamiento
     *   histórico: un item sin un campo opcional lo deja en null (equivalente a un "PUT" completo
     *   del pivote). true = un campo opcional ausente/vacío conserva el valor ya guardado en el
     *   pivote existente.
     * @param bool $mergeTenants FIX I3(b): ver doc de updateUser(). true = MERGE
     *   (syncWithoutDetaching); false (default) = sync explícito con detach (comportamiento
     *   histórico, usado por la edición individual).
     */
    protected function assignTenantsWithConfig(User $user, array $config, ?int $fallbackRoleId = null, bool $preserveWhenMissing = false, bool $mergeTenants = false): void
    {
        // Pivotes actuales (solo se consultan si hace falta preservar valores
        // ausentes; evita una query extra en el camino histórico).
        $existingPivots = ($preserveWhenMissing && $user->exists)
            ? $user->tenants()->get()->keyBy('id')
            : collect();

        $pivotData = [];
        foreach ($config as $item) {
            $tenantId = (int) $item['tenant_id'];
            $existingPivot = $existingPivots->get($tenantId)?->pivot;

            // Manejar tanto 'supervisor_id' como 'supervisors' (array)
            $supervisorId = null;
            $supervisorProvided = isset($item['supervisor_id'])
                || (isset($item['supervisors']) && is_array($item['supervisors']) && !empty($item['supervisors']));
            if (isset($item['supervisor_id'])) {
                $supervisorId = $item['supervisor_id'];
            } elseif (isset($item['supervisors']) && is_array($item['supervisors']) && !empty($item['supervisors'])) {
                // Tomar el primer supervisor si viene como array
                $supervisorId = $item['supervisors'][0];
            }
            if (!$supervisorProvided && $preserveWhenMissing && $existingPivot) {
                $supervisorId = $existingPivot->supervisor_id;
            }

            $pivotData[$tenantId] = [
                'supervisor_id' => $supervisorId,
                'is_primary' => $item['is_primary'] ?? false,
                'hire_date' => $this->resolvePivotValue($item['hire_date'] ?? null, $existingPivot?->hire_date, $preserveWhenMissing),
                'vacation_balance_initial' => $this->resolvePivotValue($item['vacation_balance_initial'] ?? null, $existingPivot?->vacation_balance_initial, $preserveWhenMissing),
                'department' => $this->resolvePivotValue($item['department'] ?? null, $existingPivot?->department, $preserveWhenMissing),
                'position' => $this->resolvePivotValue($item['position'] ?? null, $existingPivot?->position, $preserveWhenMissing),
            ];
        }

        // FIX I3(a): limpiar user_tenant_roles huérfanos de los tenants que
        // el sync haya desenganchado (ver doc del helper).
        $this->syncTenantsAndCleanOrphanRoles($user, $pivotData, $mergeTenants);

        // Asignar roles operativos por empresa. Si un item no trae role_ids
        // explícitos, se usa el rol global de respaldo (fallbackRoleId) para
        // esa empresa; si tampoco hay fallback, no se toca user_tenant_roles
        // para ese tenant (se preserva lo que ya existiera).
        foreach ($config as $item) {
            $roleIds = $item['role_ids'] ?? ($fallbackRoleId ? [$fallbackRoleId] : []);
            if (!empty($roleIds)) {
                $this->assignRoles($user, (int) $item['tenant_id'], $roleIds);
            }
        }
    }

    /**
     * FIX I3: sincroniza el pivote user_tenants del usuario y, si el sync
     * desengancha algún tenant (solo puede pasar cuando $mergeTenants es
     * false), elimina también las filas de user_tenant_roles de ese
     * usuario para esos tenants.
     *
     * Antes de este fix, tenants()->sync() desenganchaba tenants no
     * listados en $pivotData pero NO tocaba user_tenant_roles: el usuario
     * quedaba sin membresía en user_tenants para esa empresa, pero
     * hasRoleInTenant() (y por tanto getCurrentRole($tenantId) y todo el
     * middleware/autorización que depende de ella) seguía devolviendo
     * true/el rol viejo para una empresa de la que, a todos los efectos
     * visibles, el usuario ya había sido removido.
     *
     * $mergeTenants = true usa syncWithoutDetaching (nunca desengancha, ver
     * doc de updateUser()/assignTenantsWithConfig), por lo que en ese modo
     * esta limpieza es naturalmente un no-op ('detached' siempre viene
     * vacío).
     *
     * @param User $user
     * @param array<int, array<string, mixed>> $pivotData Datos de pivote keyed por tenant_id, formato esperado por BelongsToMany::sync()/syncWithoutDetaching().
     * @param bool $mergeTenants
     * @return array{attached: array<int, int|string>, detached: array<int, int|string>, updated: array<int, int|string>}
     */
    private function syncTenantsAndCleanOrphanRoles(User $user, array $pivotData, bool $mergeTenants = false): array
    {
        $syncResult = $mergeTenants
            ? $user->tenants()->syncWithoutDetaching($pivotData)
            : $user->tenants()->sync($pivotData);

        if (!empty($syncResult['detached'])) {
            UserTenantRole::where('user_id', $user->id)
                ->whereIn('tenant_id', $syncResult['detached'])
                ->delete();
        }

        // Sus empresas cambiaron: sin esto, el filtro por empresa seguiría
        // usando las anteriores hasta una hora (ver TenantAccessCache).
        TenantAccessCache::forget($user->id);

        return $syncResult;
    }

    /**
     * Resuelve el valor a persistir en el pivote user_tenants para un campo
     * "opcional por empresa" (hire_date, vacation_balance_initial,
     * department, position): si $newValue viene explícito (no vacío) se usa
     * ese; si no viene y $preserve es true, se conserva $existingValue en
     * vez de pisarlo con null. Ver doc de updateUser()/assignTenantsWithConfig.
     *
     * @param mixed $newValue
     * @param mixed $existingValue
     * @return mixed
     */
    private function resolvePivotValue($newValue, $existingValue, bool $preserve)
    {
        if ($newValue !== null && $newValue !== '') {
            return $newValue;
        }

        return $preserve ? $existingValue : null;
    }

    /**
     * Generate a temporary password.
     *
     * @return string
     */
    public function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }

    /**
     * Send welcome email with credentials.
     *
     * Despachado vía SendWelcomeEmailJob (sin delay) en vez de enviarse
     * aquí mismo: WelcomeUserMail implementa ShouldQueue, y el mailer del
     * tenant del usuario solo puede resolverse de forma segura dentro del
     * handle() del job que efectivamente lo procesa en el worker (ver
     * TenantMailerService y SendWelcomeEmailJob::handle()). Así, la
     * creación individual y la carga masiva (que sí usa delay) comparten
     * exactamente la misma ruta de envío.
     *
     * @param User $user
     * @param string $password
     * @return void
     */
    public function sendWelcomeEmail(User $user, string $password): void
    {
        try {
            \App\Jobs\SendWelcomeEmailJob::dispatch($user->id, $password);
        } catch (\Exception $e) {
            Log::warning('[UserService] Failed to dispatch welcome email job', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - email failure shouldn't prevent user creation
        }
    }

    /**
     * Assign orphan documents to a user based on document_text.
     *
     * @param User $user
     * @param string $documentText
     * @param int $tenantId
     * @return int Number of documents assigned
     */
    public function assignOrphanDocuments(User $user, string $documentText, int $tenantId): int
    {
        $orphanDocuments = Document::where('employee_document_number', $documentText)
            ->where('tenant_id', $tenantId)
            ->where('status', 'orphan')
            ->get();

        if ($orphanDocuments->isEmpty()) {
            return 0;
        }

        Log::info('[UserService] Assigning orphan documents', [
            'user_id' => $user->id,
            'document_text' => $documentText,
            'tenant_id' => $tenantId,
            'orphan_count' => $orphanDocuments->count(),
        ]);

        foreach ($orphanDocuments as $document) {
            $document->user_id = $user->id;
            $document->status = $document->requires_signature ? 'pending' : 'active';
            $document->save();

            Log::info('[UserService] Orphan document assigned', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'new_status' => $document->status,
            ]);
        }

        return $orphanDocuments->count();
    }

    /**
     * Regla centralizada de "quién puede ADMINISTRAR (editar, resetear
     * contraseña, eliminar) a quién" desde Gestión de Usuarios. Reemplaza a
     * la vieja canAccessUser(), que solo miraba solapamiento de tenant sin
     * ninguna jerarquía de rol — permitía, p. ej., que un admin_tenant
     * editara a otro admin_tenant o a sí mismo (Decisión C1 del plan de
     * observaciones del cliente, 2026-07).
     *
     * Reglas, en orden:
     * 1. root administra a cualquiera.
     * 2. nadie se administra a sí mismo desde esta pantalla (el perfil
     *    propio va por /profile). Ver excepción de solo-lectura que aplican
     *    los callers de show()/subordinates() (canManageUser() || self).
     * 3. nadie no-root puede administrar a otro root, a un usuario con rol
     *    admin_tenant en CUALQUIER empresa, ni a un usuario con rol admin en
     *    CUALQUIER empresa: editar users.* (email, password, estado) tiene
     *    efecto global, no acotado a una empresa (Observación 1 2026-08:
     *    antes de esto un admin_tenant seguía pudiendo administrar cuentas
     *    admin en la empresa compartida — ese loophole cross-tenant es lo
     *    que cierra esta regla; sigue pudiendo ASIGNAR el rol admin al
     *    crear/editar, ver ASSIGNABLE_ROLES, solo ya no administrar la
     *    cuenta).
     * 4. si no, hace falta una empresa compartida T donde el rol de $actor
     *    en T sea una clave de MANAGEABLE_ROLES y el rol de $target en T
     *    esté en el conjunto que ese rol puede administrar.
     *
     * @param User $actor
     * @param User $target
     * @return bool
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isRoot()) {
            return true;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if (
            $target->isRoot()
            || $this->hasRoleInAnyTenant($target, 'admin_tenant')
            || $this->hasRoleInAnyTenant($target, 'admin')
        ) {
            return false;
        }

        foreach ($actor->tenants as $tenant) {
            $allowedTargetRoles = self::MANAGEABLE_ROLES[User::roleForTenant($actor, $tenant->id)] ?? null;

            if ($allowedTargetRoles === null) {
                continue;
            }

            $targetRole = User::roleForTenant($target, $tenant->id);
            if ($targetRole !== null && in_array($targetRole, $allowedTargetRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿$user tiene el rol $roleName en AL MENOS UNA empresa? Usado por
     * canManageUser() para blindar a los admin_tenant y a los admin (regla
     * 3): un admin_tenant o un admin de la empresa A sigue siendo intocable
     * para un actor no-root aunque lo comparta con él en una empresa B con
     * un rol distinto.
     */
    private function hasRoleInAnyTenant(User $user, string $roleName): bool
    {
        return $user->tenantRoles()
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->exists();
    }
}
