<?php

namespace App\Services;

use App\Exceptions\UserCreationException;
use App\Mail\EmailChangedNotificationMail;
use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserService
{
    /**
     * ID convencional del rol 'root' (ver RoleSeeder: es el primer rol
     * insertado). Root es global: no tiene empresas ni roles por empresa.
     */
    protected const ROOT_ROLE_ID = 1;

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
     * @return User
     */
    public function updateUser(User $user, array $data): User
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
                    $this->assignTenantsWithConfig($user, $data['tenants_config'], $newRoleId);
                } elseif (isset($data['tenant_ids']) && is_array($data['tenant_ids'])) {
                    // Legacy/Simple assignment without supervisors update (or keeping existing)
                    // But sync deletes existing pivot data. We should be careful.
                    // For now, simple behavior:
                    $primaryTenantId = $data['primary_tenant_id'] ?? $data['tenant_ids'][0];
                    $pivotData = [];
                    foreach ($data['tenant_ids'] as $tenantId) {
                        $pivotData[$tenantId] = ['is_primary' => $tenantId == $primaryTenantId];
                    }
                    $user->tenants()->sync($pivotData);

                    if ($newRoleId) {
                        foreach ($data['tenant_ids'] as $tenantId) {
                            $this->assignRoles($user, (int) $tenantId, [$newRoleId]);
                        }
                    }
                } elseif (isset($data['tenant_id'])) {
                    $user->tenants()->sync([
                        $data['tenant_id'] => ['is_primary' => true]
                    ]);

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
        $user->roles()->sync([
            $roleId => [
                'granted_by' => $grantedBy?->id,
                'granted_at' => now(),
            ],
        ]);
    }

    /**
     * Assign operational roles to a user within a specific tenant
     * (user_tenant_roles). Reemplaza cualquier rol previo del usuario en esa
     * empresa por el conjunto indicado (comportamiento tipo "sync" por
     * empresa).
     *
     * @param User $user
     * @param int $tenantId
     * @param array<int> $roleIds Roles operativos (admin, client, aprobador, administrador_clientes)
     * @return void
     */
    protected function assignRoles(User $user, int $tenantId, array $roleIds): void
    {
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
    }

    /**
     * Mantiene sincronizado el rol "global" de respaldo del usuario (tabla
     * user_roles) con la unión de los roles operativos que tiene asignados
     * en todas sus empresas (user_tenant_roles).
     *
     * Esto es una medida de transición: la fuente de verdad para roles
     * operativos es user_tenant_roles, pero gran parte del código existente
     * (middleware CheckRole, StoreUserRequest/UpdateUserRequest::authorize,
     * reportes, etc.) todavía resuelve permisos vía
     * getCurrentRole()/getCurrentRoles() sin contexto de empresa. Sin este
     * respaldo, esos usuarios quedarían sin rol global y esas verificaciones
     * fallarían para cualquier usuario operativo creado o actualizado a
     * través de este servicio.
     *
     * TODO(RP1-D): eliminar este respaldo cuando el frontend/middleware
     * resuelva el rol vía tenant activo (header X-Tenant-Ids) en lugar de
     * depender de user_roles para usuarios operativos. Nótese que, si un
     * usuario tiene roles distintos en distintas empresas, este respaldo
     * expone la UNIÓN de todos ellos (no solo el de la empresa "activa"),
     * lo cual es más permisivo de lo ideal hasta que ese workstream aterrice
     * el chequeo por tenant.
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
     *   ]
     * @param int|null $fallbackRoleId Rol a aplicar en las empresas cuyo item no traiga 'role_ids'
     *   (compatibilidad con la carga masiva, que hoy envía un único role_id global
     *   junto con tenants_config sin desglose de roles por empresa).
     */
    protected function assignTenantsWithConfig(User $user, array $config, ?int $fallbackRoleId = null): void
    {
        $pivotData = [];
        foreach ($config as $item) {
            // Manejar tanto 'supervisor_id' como 'supervisors' (array)
            $supervisorId = null;
            if (isset($item['supervisor_id'])) {
                $supervisorId = $item['supervisor_id'];
            } elseif (isset($item['supervisors']) && is_array($item['supervisors']) && !empty($item['supervisors'])) {
                // Tomar el primer supervisor si viene como array
                $supervisorId = $item['supervisors'][0];
            }

            $pivotData[$item['tenant_id']] = [
                'supervisor_id' => $supervisorId,
                'is_primary' => $item['is_primary'] ?? false,
                'hire_date' => $item['hire_date'] ?? null,
                'vacation_balance_initial' => $item['vacation_balance_initial'] ?? null,
            ];
        }
        $user->tenants()->sync($pivotData);

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
     * Check if current user can access target user.
     *
     * @param User $currentUser
     * @param User $targetUser
     * @return bool
     */
    public function canAccessUser(User $currentUser, User $targetUser): bool
    {
        if ($currentUser->isRoot()) {
            return true;
        }

        // Check if they share at least one tenant
        return $currentUser->tenants->pluck('id')
            ->intersect($targetUser->tenants->pluck('id'))
            ->count() > 0;
    }
}
