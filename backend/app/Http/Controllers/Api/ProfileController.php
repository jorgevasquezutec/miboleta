<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Get current user profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'tenants', 'immediateSupervisor']);

        return response()->json([
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
            ]),
            'immediate_supervisor' => $user->immediateSupervisor ? [
                'id' => $user->immediateSupervisor->id,
                'full_name' => $user->immediateSupervisor->full_name,
                'email' => $user->immediateSupervisor->email,
            ] : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);
        $user->load(['roles', 'tenants']);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => $user,
        ]);
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if (!empty($user->attributes['avatar_url'])) {
            Storage::disk('public')->delete($user->attributes['avatar_url']);
        }

        // Store new avatar
        $file = $request->file('avatar');
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');

        // Update user avatar_url with just the path (accessor will generate full URL)
        $user->update(['avatar_url' => $path]);

        // Reload to get accessor value
        $user->refresh();

        return response()->json([
            'message' => 'Foto de perfil actualizada exitosamente',
            'avatar_url' => $user->avatar_url, // This will be the full URL from accessor
        ]);
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!empty($user->attributes['avatar_url'])) {
            Storage::disk('public')->delete($user->attributes['avatar_url']);
            $user->update(['avatar_url' => null]);
        }

        return response()->json([
            'message' => 'Foto de perfil eliminada exitosamente',
        ]);
    }
}
