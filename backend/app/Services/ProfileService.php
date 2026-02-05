<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Get user profile data.
     *
     * @param User $user
     * @return array
     */
    public function getProfile(User $user): array
    {
        $user->load(['roles', 'tenants']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'document_type' => $user->document_type,
            'document_text' => $user->document_text,
            'phone' => $user->phone,
            'status' => $user->status,
            'role' => $user->getCurrentRole(),
            'roles' => $user->getCurrentRoles(),
            'tenants' => $user->tenants->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'ruc' => $t->ruc,
                'logo_url' => $t->logo_url,
                'is_primary' => $t->pivot->is_primary,
                'supervisor_id' => $t->pivot->supervisor_id ?? null,
            ]),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Update user profile.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);
        $user->load(['roles', 'tenants']);

        return $user;
    }

    /**
     * Upload user avatar.
     *
     * @param User $user
     * @param UploadedFile $file
     * @return string The new avatar URL
     */
    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        // Delete old avatar if exists
        $this->deleteAvatarFile($user);

        // Generate filename
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Store new avatar
        $path = $file->storeAs('avatars', $filename, 'public');

        // Update user
        $user->update(['avatar_url' => $path]);
        $user->refresh();

        return $user->avatar_url;
    }

    /**
     * Delete user avatar.
     *
     * @param User $user
     * @return bool
     */
    public function deleteAvatar(User $user): bool
    {
        if ($this->deleteAvatarFile($user)) {
            $user->update(['avatar_url' => null]);
            return true;
        }

        return false;
    }

    /**
     * Delete avatar file from storage.
     *
     * @param User $user
     * @return bool
     */
    protected function deleteAvatarFile(User $user): bool
    {
        $avatarPath = $user->getRawOriginal('avatar_url');
        if (!empty($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
            return true;
        }

        return false;
    }
}
