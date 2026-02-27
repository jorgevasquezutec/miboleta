<?php

namespace App\Services;

use App\Exceptions\UserCreationException;
use App\Mail\EmailChangedNotificationMail;
use App\Mail\WelcomeUserMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserService
{
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
                'status' => $data['status'] ?? 'active',
                'must_change_password' => true,
            ]);

            // Assign role
            $this->assignRoles($user, [$data['role_id']], $creator);

            // Assign tenants with supervisors
            if (isset($data['tenants_config']) && is_array($data['tenants_config'])) {
                $this->assignTenantsWithConfig($user, $data['tenants_config']);
            } elseif (isset($data['tenant_id'])) {
                // Fallback for simple creation
                $this->assignTenants($user, [$data['tenant_id']], true);
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

            return $user->load(['roles', 'tenants']);

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
            $oldRoleId = $user->roles()->first()?->id;
            $userAuth = Auth::user(); // --- IGNORE ---

            // Detect email change before update
            $emailChanged = isset($data['email']) && $data['email'] !== $user->email;
            $oldEmail = $user->email;

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Handle role change
            if (isset($data['role_id']) && $data['role_id'] != $oldRoleId) {
                // Si el nuevo rol es root (role_id = 1), remover todos los tenants
                if ($data['role_id'] == 1) {
                    $user->tenants()->detach();
                    unset($data['tenants_config'], $data['tenant_id'], $data['tenant_ids']);
                } else {
                    // Si cambió de root a otro rol y no viene config, asegurar tenant
                    if ($oldRoleId == 1 && isset($data['tenant_id']) && !isset($data['tenants_config'])) {
                        $this->assignTenants($user, [$data['tenant_id']], true);
                    }
                }

                // Actualizar el rol
                $user->roles()->sync([
                    $data['role_id'] => [
                        'granted_by' => $userAuth->id,
                        'granted_at' => now(),
                    ]
                ]);
            }

            // Handle tenant assignment for non-root users
            $currentRoleId = $data['role_id'] ?? $oldRoleId;
            if ($currentRoleId != 1) { // Not root
                if (isset($data['tenants_config']) && is_array($data['tenants_config'])) {
                    $this->assignTenantsWithConfig($user, $data['tenants_config']);
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
                } elseif (isset($data['tenant_id'])) {
                    $user->tenants()->sync([
                        $data['tenant_id'] => ['is_primary' => true]
                    ]);
                }
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

            return $user->load(['roles', 'tenants']);
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
     * Assign roles to a user.
     *
     * @param User $user
     * @param array $roleIds
     * @param User|null $grantedBy
     * @return void
     */
    protected function assignRoles(User $user, array $roleIds, ?User $grantedBy): void
    {
        $pivotData = [];
        foreach ($roleIds as $roleId) {
            $pivotData[$roleId] = [
                'granted_by' => $grantedBy?->id,
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
     * Assign tenants with supervisor configuration.
     * 
     * @param User $user
     * @param array $config Array of ['tenant_id' => int, 'supervisor_id' => ?int, 'is_primary' => bool]
     */
    protected function assignTenantsWithConfig(User $user, array $config): void
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
            ];
        }
        $user->tenants()->sync($pivotData);
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
     * @param User $user
     * @param string $password
     * @return void
     */
    public function sendWelcomeEmail(User $user, string $password): void
    {
        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $password));
        } catch (\Exception $e) {
            Log::warning('[UserService] Failed to send welcome email', [
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
