<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Perfil",
 *     description="Gestión del perfil del usuario autenticado"
 * )
 */
class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {
    }

    /**
     * Get current user profile
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->profileService->getProfile($request->user())
        );
    }

    /**
     * Update user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $avatarUrl = $this->profileService->uploadAvatar(
            $request->user(),
            $request->file('avatar')
        );

        return response()->json([
            'message' => 'Foto de perfil actualizada exitosamente',
            'avatar_url' => $avatarUrl,
        ]);
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->profileService->deleteAvatar($request->user());

        return response()->json([
            'message' => 'Foto de perfil eliminada exitosamente',
        ]);
    }
}
