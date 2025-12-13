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
     * @OA\Get(
     *     path="/api/profile",
     *     tags={"Perfil"},
     *     summary="Obtener perfil del usuario",
     *     description="Retorna el perfil completo del usuario autenticado",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Perfil del usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", format="uuid"),
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="full_name", type="string", example="Juan Pérez"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="avatar_url", type="string", nullable=true),
     *             @OA\Property(property="document_type", type="string", example="DNI"),
     *             @OA\Property(property="document_text", type="string", example="12345678"),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="role", type="string", example="client"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="tenants", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="immediate_supervisor", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->profileService->getProfile($request->user())
        );
    }

    /**
     * @OA\Put(
     *     path="/api/profile",
     *     tags={"Perfil"},
     *     summary="Actualizar perfil",
     *     description="Actualiza los datos del perfil del usuario autenticado",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="phone", type="string", example="999888777")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perfil actualizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Perfil actualizado exitosamente"),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
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
     * @OA\Post(
     *     path="/api/profile/avatar",
     *     tags={"Perfil"},
     *     summary="Subir foto de perfil",
     *     description="Sube una nueva foto de perfil (avatar)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"avatar"},
     *                 @OA\Property(property="avatar", type="string", format="binary", description="Imagen (jpg, png, gif, max 2MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Avatar subido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Foto de perfil actualizada exitosamente"),
     *             @OA\Property(property="avatar_url", type="string", example="http://localhost/storage/avatars/avatar_1_1234567890.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Archivo inválido"
     *     )
     * )
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
     * @OA\Delete(
     *     path="/api/profile/avatar",
     *     tags={"Perfil"},
     *     summary="Eliminar foto de perfil",
     *     description="Elimina la foto de perfil del usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Avatar eliminado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Foto de perfil eliminada exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->profileService->deleteAvatar($request->user());

        return response()->json([
            'message' => 'Foto de perfil eliminada exitosamente',
        ]);
    }
}
