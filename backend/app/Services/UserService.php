<?php

namespace App\Services;

use App\Exceptions\UserCreationException;
use App\Mail\WelcomeUserMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Create a new user with all related data.
     *
     * @param array $data Validated user data
     * @param User $creator The user creating this new user
     * @return User
     * @throws UserCreationException
     */
    public function createUser(array $data, User $creator): User
    {
        DB::beginTransaction();

        try {
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
                'immediate_supervisor_id' => $data['immediate_supervisor_id'] ?? null,
                'status' => 'active',
                'must_change_password' => true,
            ]);

            // Assign role
            $this->assignRoles($user, [$data['role_id']], $creator);

            // Assign tenant
            $this->assignTenants($user, [$data['tenant_id']], true);

            // Send welcome email
            $this->sendWelcomeEmail($user, $temporaryPassword);

            // Assign orphan documents if document_text is provided
            if (!empty($data['document_text'])) {
                $this->assignOrphanDocuments($user, $data['document_text'], $data['tenant_id']);
            }

            DB::commit();

            return $user->load(['roles', 'tenants', 'immediateSupervisor']);

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

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Handle role change
            if (isset($data['role_id']) && $data['role_id'] != $oldRoleId) {
                // Si el nuevo rol es root (role_id = 1), remover todos los tenants
                if ($data['role_id'] == 1) {
                    $user->tenants()->detach();
                    // No incluir tenant_id en la actualización
                    unset($data['tenant_id']);
                    unset($data['tenant_ids']);
                    unset($data['primary_tenant_id']);
                } else {
                    // Si cambió de root a otro rol, asignar tenant obligatorio
                    if ($oldRoleId == 1 && isset($data['tenant_id'])) {
                        $this->assignTenants($user, [$data['tenant_id']], true);
                    }
                }

                // Actualizar el rol
                $user->roles()->sync([
                    $data['role_id'] => [
                        'granted_by' => auth()->id(),
                        'granted_at' => now(),
                    ]
                ]);
            }

            // Handle tenant assignment for non-root users
            if (isset($data['tenant_ids']) && is_array($data['tenant_ids']) && $data['role_id'] != 1) {
                $primaryTenantId = $data['primary_tenant_id'] ?? $data['tenant_ids'][0];

                $pivotData = [];
                foreach ($data['tenant_ids'] as $tenantId) {
                    $pivotData[$tenantId] = [
                        'is_primary' => $tenantId == $primaryTenantId,
                    ];
                }

                $user->tenants()->sync($pivotData);
            } elseif (isset($data['tenant_id']) && $data['role_id'] != 1) {
                // Fallback: single tenant assignment
                $user->tenants()->sync([
                    $data['tenant_id'] => ['is_primary' => true]
                ]);
            }

            // Remove tenant-related fields from user update
            unset($data['role_id'], $data['tenant_id'], $data['tenant_ids'], $data['primary_tenant_id']);

            $user->update($data);

            // If document_text changed, assign orphan documents
            if (isset($data['document_text']) && $data['document_text'] !== $oldDocumentText) {
                $primaryTenant = $user->tenants()->wherePivot('is_primary', true)->first();
                if ($primaryTenant) {
                    $this->assignOrphanDocuments($user, $data['document_text'], $primaryTenant->id);
                }
            }

            DB::commit();

            return $user->load(['roles', 'tenants', 'immediateSupervisor']);
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
    public function assignRoles(User $user, array $roleIds, ?User $grantedBy = null): void
    {
        $pivotData = [];
        foreach ($roleIds as $roleId) {
            $pivotData[$roleId] = [
                'granted_by' => $grantedBy?->id,
                'granted_at' => now(),
            ];
        }

        $user->roles()->syncWithoutDetaching($pivotData);
    }

    /**
     * Assign tenants to a user.
     *
     * @param User $user
     * @param array $tenantIds
     * @param bool $isPrimary
     * @return void
     */
    public function assignTenants(User $user, array $tenantIds, bool $isPrimary = false): void
    {
        $pivotData = [];
        foreach ($tenantIds as $index => $tenantId) {
            $pivotData[$tenantId] = [
                'is_primary' => $isPrimary && $index === 0, // Only first is primary
            ];
        }

        $user->tenants()->syncWithoutDetaching($pivotData);
    }

    /**
     * Assign supervisor to a user.
     *
     * @param User $user
     * @param int|null $supervisorId
     * @return User
     * @throws \InvalidArgumentException
     */
    public function assignSupervisor(User $user, ?int $supervisorId): User
    {
        if ($supervisorId) {
            $supervisor = User::find($supervisorId);

            // Validate no cycle is created
            if ($supervisor && $supervisor->immediateSupervisor && $supervisor->immediateSupervisor->id === $user->id) {
                throw new \InvalidArgumentException('No se puede crear un ciclo de supervisión');
            }
        }

        $user->immediate_supervisor_id = $supervisorId;
        $user->save();

        return $user->load('immediateSupervisor');
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
