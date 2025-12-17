<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Notificaciones",
 *     description="Endpoints para gestión de notificaciones"
 * )
 */
class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     tags={"Notificaciones"},
     *     summary="Listar notificaciones",
     *     description="Lista las notificaciones del usuario autenticado",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="unread_only",
     *         in="query",
     *         description="Solo notificaciones no leídas",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrar por tipo de notificación",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Lista de notificaciones")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del header X-Tenant-Ids o usar los tenants del usuario
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'unread_only' => $request->boolean('unread_only'),
            'type' => $request->type,
            'per_page' => $request->get('per_page', 15),
        ];

        $notifications = $this->notificationService->getForUser($user, $filters);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread-count",
     *     tags={"Notificaciones"},
     *     summary="Contador de no leídas",
     *     description="Obtiene el número de notificaciones no leídas",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contador de notificaciones")
     * )
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del header X-Tenant-Ids o usar los tenants del usuario
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $count = $this->notificationService->getUnreadCount($user, $tenantIds);

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/notifications/{id}/read",
     *     tags={"Notificaciones"},
     *     summary="Marcar como leída",
     *     description="Marca una notificación como leída",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Notificación marcada como leída"),
     *     @OA\Response(response=404, description="Notificación no encontrada")
     * )
     */
    public function markAsRead(int $id): JsonResponse
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->forUser($user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notificación no encontrada',
            ], 404);
        }

        $notification = $this->notificationService->markAsRead($notification);

        return response()->json([
            'message' => 'Notificación marcada como leída',
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/notifications/read-all",
     *     tags={"Notificaciones"},
     *     summary="Marcar todas como leídas",
     *     description="Marca todas las notificaciones del usuario como leídas",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Notificaciones marcadas como leídas")
     * )
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del header X-Tenant-Ids o usar los tenants del usuario
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $count = $this->notificationService->markAllAsRead($user, $tenantIds);

        return response()->json([
            'message' => "Se marcaron {$count} notificaciones como leídas",
            'count' => $count,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     tags={"Notificaciones"},
     *     summary="Eliminar notificación",
     *     description="Elimina una notificación",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Notificación eliminada"),
     *     @OA\Response(response=404, description="Notificación no encontrada")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->forUser($user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'message' => 'Notificación no encontrada',
            ], 404);
        }

        $this->notificationService->delete($notification);

        return response()->json([
            'message' => 'Notificación eliminada',
        ]);
    }
}
