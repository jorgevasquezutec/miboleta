<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Listar usuarios",
     *     description="Retorna usuarios filtrados por tenant. Root ve todos, Admin ve solo de sus tenants.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="ID del tenant (opcional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Lista de usuarios"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query base
        $query = User::with(['roles', 'tenants', 'immediateSupervisor']);

        // Root ve todos los usuarios
        if (!$user->isRoot()) {
            // Admin y Client solo ven usuarios de sus tenants
            $tenantIds = $user->tenants->pluck('id');
            $query->whereHas('tenants', function ($q) use ($tenantIds) {
                $q->whereIn('tenants.id', $tenantIds);
            });
        }

        // Filtro por tenant específico
        if ($request->has('tenant_id')) {
            $query->whereHas('tenants', function ($q) use ($request) {
                $q->where('tenants.id', $request->tenant_id);
            });
        }

        // Búsqueda por nombre, email o documento
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document_text', 'like', "%{$search}%");
            });
        }

        // Filtro por estado (solo si tiene valor)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Paginación
        $perPage = $request->get('per_page', 10);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'last_name' => $u->last_name,
                    'full_name' => $u->full_name,
                    'email' => $u->email,
                    'document_type' => $u->document_type,
                    'document_text' => $u->document_text,
                    'phone' => $u->phone,
                    'status' => $u->status,
                    'role' => $u->getCurrentRole(),
                    'roles' => $u->getCurrentRoles(),
                    'tenants' => $u->tenants->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'ruc' => $t->ruc ?? '',
                        'is_primary' => $t->pivot->is_primary ?? false,
                    ]),
                    'immediate_supervisor' => $u->immediateSupervisor ? [
                        'id' => $u->immediateSupervisor->id,
                        'name' => $u->immediateSupervisor->name,
                        'full_name' => $u->immediateSupervisor->full_name,
                        'email' => $u->immediateSupervisor->email,
                    ] : null,
                    'created_at' => $u->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Crear usuario",
     *     description="Crea un nuevo usuario con contraseña aleatoria y envía email de bienvenida",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","role_id","tenant_id"},
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", example="juan@example.com"),
     *             @OA\Property(property="document_type", type="string", example="DNI"),
     *             @OA\Property(property="document_text", type="string", example="12345678"),
     *             @OA\Property(property="phone", type="string", example="987654321"),
     *             @OA\Property(property="immediate_supervisor_id", type="integer", example=2),
     *             @OA\Property(property="role_id", type="integer", example=3),
     *             @OA\Property(property="tenant_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'document_type' => 'nullable|string|in:dni,ruc,ce,passport',
            'document_text' => 'nullable|string|unique:users,document_text',
            'phone' => 'nullable|string|max:20',
            'immediate_supervisor_id' => 'nullable|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        // Generar contraseña aleatoria
        $temporaryPassword = Str::random(12);

        // Crear usuario
        $user = User::create([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($temporaryPassword),
            'document_type' => $validated['document_type'] ?? null,
            'document_text' => $validated['document_text'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'immediate_supervisor_id' => $validated['immediate_supervisor_id'] ?? null,
            'status' => 'active',
            'must_change_password' => true, // Forzar cambio en primer login
        ]);

        // Asignar rol
        $user->roles()->attach($validated['role_id'], [
            'granted_by' => $request->user()->id,
            'granted_at' => now(),
        ]);

        // Asignar a tenant
        $user->tenants()->attach($validated['tenant_id'], [
            'is_primary' => true,
        ]);

        // Enviar email de bienvenida con credenciales
        Mail::to($user->email)->send(new WelcomeUserMail($user, $temporaryPassword));

        $user->load(['roles', 'tenants', 'immediateSupervisor']);

        return response()->json([
            'message' => 'Usuario creado exitosamente. Se ha enviado un correo con las credenciales.',
            'user' => $user,
            'email_sent' => true,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Obtener usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle del usuario"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function show(Request $request, $id)
    {
        $user = User::with(['roles', 'tenants', 'immediateSupervisor', 'subordinates'])->findOrFail($id);

        // Verificar acceso
        $currentUser = $request->user();
        if (!$currentUser->isRoot() && !$currentUser->tenants->pluck('id')->intersect($user->tenants->pluck('id'))->count()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'document_type' => $user->document_type,
            'document_text' => $user->document_text,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->getCurrentRoles(),
            'tenants' => $user->tenants->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'ruc' => $t->ruc,
                'is_primary' => $t->pivot->is_primary,
            ]),
            'immediate_supervisor' => $user->immediateSupervisor ? [
                'id' => $user->immediateSupervisor->id,
                'full_name' => $user->immediateSupervisor->full_name,
                'email' => $user->immediateSupervisor->email,
            ] : null,
            'subordinates' => $user->subordinates->map(fn($s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'email' => $s->email,
            ]),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'last_login_at' => $user->last_login_at,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Actualizar usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Usuario actualizado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'document_type' => 'nullable|string|in:DNI,RUC,CE,Pasaporte',
            'document_text' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'immediate_supervisor_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        $user->load(['roles', 'tenants', 'immediateSupervisor']);

        // Retornar usuario con formato completo (igual que /me)
        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'document_type' => $user->document_type,
                'document_text' => $user->document_text,
                'phone' => $user->phone,
                'status' => $user->status,
                'must_change_password' => $user->must_change_password,
                'role' => $user->getCurrentRole(),
                'roles' => $user->getCurrentRoles(),
                'tenants' => $user->tenants->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'ruc' => $tenant->ruc,
                        'logo_url' => $tenant->logo_url,
                        'is_primary' => $tenant->pivot->is_primary,
                    ];
                }),
                'primary_tenant' => $user->primaryTenant() ? [
                    'id' => $user->primaryTenant()->id,
                    'name' => $user->primaryTenant()->name,
                    'ruc' => $user->primaryTenant()->ruc,
                ] : null,
                'immediate_supervisor' => $user->immediateSupervisor ? [
                    'id' => $user->immediateSupervisor->id,
                    'full_name' => $user->immediateSupervisor->full_name,
                    'email' => $user->immediateSupervisor->email,
                ] : null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }


    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Eliminar usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Usuario eliminado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Soft delete
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/subordinates",
     *     tags={"Usuarios"},
     *     summary="Obtener subordinados del usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista de subordinados")
     * )
     */
    public function subordinates($id)
    {
        $user = User::with('subordinates')->findOrFail($id);

        return response()->json($user->subordinates->map(fn($s) => [
            'id' => $s->id,
            'full_name' => $s->full_name,
            'email' => $s->email,
            'phone' => $s->phone,
            'status' => $s->status,
        ]));
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}/supervisor",
     *     tags={"Usuarios"},
     *     summary="Asignar supervisor inmediato",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="supervisor_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Supervisor asignado")
     * )
     */
    public function assignSupervisor(Request $request, $id)
    {
        $validated = $request->validate([
            'supervisor_id' => 'nullable|exists:users,id',
        ]);

        $user = User::findOrFail($id);

        // Validar que no se cree un ciclo
        if ($validated['supervisor_id']) {
            $supervisor = User::find($validated['supervisor_id']);

            // No puede ser subordinado de su propio subordinado
            if ($supervisor->immediateSupervisor && $supervisor->immediateSupervisor->id === $user->id) {
                return response()->json([
                    'message' => 'No se puede crear un ciclo de supervisión',
                ], 422);
            }
        }

        $user->immediate_supervisor_id = $validated['supervisor_id'];
        $user->save();

        return response()->json([
            'message' => 'Supervisor asignado exitosamente',
            'user' => $user->load('immediateSupervisor'),
        ]);
    }
}
