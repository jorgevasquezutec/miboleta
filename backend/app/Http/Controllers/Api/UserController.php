<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserCreationException;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Services\ActiveTenantResolver;
use App\Services\AuditService;
use App\Services\UserService;
use App\Services\VacationBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Usuarios",
 *     description="Gestión de usuarios del sistema"
 * )
 */
class UserController extends Controller
{
    /**
     * Campos auditados al crear/editar/eliminar usuarios.
     */
    private const AUDITED_FIELDS = [
        'name', 'last_name', 'email', 'document_type', 'document_text', 'phone', 'status',
    ];

    public function __construct(
        protected UserService $userService,
        protected AuditService $auditService,
        protected ActiveTenantResolver $activeTenantResolver,
        protected VacationBalanceService $vacationBalanceService
    ) {
    }

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

        // E1 [seguridad]: este endpoint no llamaba a authorize() en ningún
        // punto — cualquier autenticado (incluido un 'client') podía listar
        // usuarios vía API; la ability 'users.view_list' solo se aplicaba en
        // el frontend (ProtectedRoute). Es un endpoint de listado sin un
        // recurso puntual del cual tomar el tenant, así que corresponde
        // ActiveTenantResolver (empresa activa del switcher / query
        // ?tenant_id para root), no un tenant de recurso.
        $this->authorize('users.view_list', $this->activeTenantResolver->resolve($request, $user));

        // Query base
        $query = User::with(['roles', 'tenants', 'tenantRoles.role']);

        // Techo de visibilidad: un no-root nunca ve más allá de sus empresas.
        // Root no tiene techo (ve el catálogo completo de la plataforma).
        if (!$user->isRoot()) {
            $tenantIds = $user->tenants->pluck('id');
            $query->whereHas('tenants', function ($q) use ($tenantIds) {
                $q->whereIn('tenants.id', $tenantIds);
            });
        }

        // C1: para no-root, ocultar del listado la fila propia (la gestión de
        // la cuenta propia va por /profile, no por Gestión de Usuarios) y
        // cualquier usuario con rol admin_tenant en CUALQUIER empresa (solo
        // root administra cuentas admin_tenant — ver
        // UserService::canManageUser). Root no tiene este filtro: ve el
        // catálogo completo, incluidos los admin_tenant y su propia fila si
        // aplicara.
        if (!$user->isRoot()) {
            $query->where('users.id', '!=', $user->id)
                ->whereDoesntHave('tenantRoles', function ($q) {
                    $q->whereHas('role', fn ($r) => $r->where('name', 'admin_tenant'));
                });
        }

        // B3: empresa activa efectiva de este listado, para el saldo de
        // vacaciones en lote de más abajo — captura EXACTAMENTE el mismo
        // tenant que ya decide el whereHas de cada rama (no una resolución
        // nueva), para que "empresa que scopea el listado" y "empresa cuyo
        // saldo se muestra" nunca diverjan. null cuando ninguna rama estrecha
        // a una sola empresa (root en modo "todas las empresas"): ahí el
        // saldo por usuario es ambiguo (ver VacationBalanceService).
        $activeTenantId = null;

        if ($request->filled('tenant_id')) {
            // Override explícito a una empresa concreta. Lo usan el dropdown de
            // root y SupervisorSelector, que consulta ?tenant_id por CADA empresa
            // del formulario —incluidas las que no son la activa—, así que el
            // param tiene que ganarle al header en vez de intersecarse con él.
            // Para un no-root sigue acotado por el whereHas de arriba, o sea no
            // puede alcanzar una empresa ajena.
            $activeTenantId = (int) $request->tenant_id;
            $query->whereHas('tenants', function ($q) use ($request) {
                $q->where('tenants.id', $request->tenant_id);
            });
        } else {
            // Default: la empresa activa del switcher (header X-Tenant-Ids, ya
            // validado por TenantFilter en `_tenant_filter_ids`). Es el mismo
            // contrato que TenantFilterScope aplica a Document/VacationRequest/
            // UserBatch, adaptado a mano porque User no tiene columna tenant_id
            // (su relación con empresas es m2m vía el pivote user_tenants).
            // Sin header (cliente antiguo) no se estrecha nada: queda el techo.
            $filterIds = $request->input('_tenant_filter_ids');
            if (is_array($filterIds) && !empty($filterIds)) {
                $activeTenantId = (int) $filterIds[0];
                $query->whereHas('tenants', function ($q) use ($filterIds) {
                    $q->whereIn('tenants.id', $filterIds);
                });
            }
        }

        // Búsqueda por nombre completo, email o documento. Nombre completo
        // vía User::scopeMatchingFullName: antes 'name' y 'last_name' se
        // comparaban por separado, así que "Juan Pérez" no encontraba al
        // empleado con name=Juan, last_name=Pérez.
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->matchingFullName($search)
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

        // B3: saldo de vacaciones de la página actual, en 2 queries totales
        // (ver VacationBalanceService::getBalancesForUsers) — sin importar
        // per_page. Solo cuando hay una empresa activa inequívoca; en modo
        // "todas las empresas" (root) no se calcula: cada usuario listado
        // podría pertenecer a empresas distintas con hire_date/saldo propios,
        // y sumarlos no tendría significado legal (ver SPEC-VACACIONES v2).
        $vacationBalances = $activeTenantId
            ? $this->vacationBalanceService->getBalancesForUsers($users->getCollection(), $activeTenantId)
            : [];

        return response()->json([
            'data' => $users->map(function ($u) use ($user, $vacationBalances) {
                // 🔒 SECURITY: Filter tenants to only show those the current admin has access to
                $visibleTenants = $u->tenants;

                if (!$user->isRoot()) {
                    // Non-root users can only see tenants they have access to
                    $allowedTenantIds = $user->tenants->pluck('id')->toArray();
                    $visibleTenants = $u->tenants->filter(function ($t) use ($allowedTenantIds) {
                        return in_array($t->id, $allowedTenantIds);
                    });
                }

                // Roles por empresa (user_tenant_roles), agrupados una sola vez
                // por usuario para evitar N+1 al mapear cada tenant de más abajo.
                $tenantRolesByTenant = $u->tenantRoles->groupBy('tenant_id');

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
                    'tenants' => $visibleTenants->map(function($t) use ($tenantRolesByTenant) {
                        $supervisorId = $t->pivot->supervisor_id ?? null;
                        $supervisor = null;

                        // Cargar información del supervisor si existe
                        if ($supervisorId) {
                            $supervisorUser = \App\Models\User::find($supervisorId);
                            if ($supervisorUser) {
                                $supervisor = [
                                    'id' => $supervisorUser->id,
                                    'name' => $supervisorUser->name,
                                    'full_name' => $supervisorUser->full_name,
                                    'email' => $supervisorUser->email,
                                ];
                            }
                        }

                        $tenantRoles = $tenantRolesByTenant->get($t->id) ?? collect();
                        $tenantRoleNames = $tenantRoles->pluck('role.name')->filter()->values();

                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'ruc' => $t->ruc ?? '',
                            // Cast explícito: el pivote entrega el 0/1 de la BD y
                            // el contrato (User.ts) declara boolean. Sin esto, un
                            // `{x.is_primary && ...}` en JSX renderiza el 0.
                            'is_primary' => (bool) ($t->pivot->is_primary ?? false),
                            'supervisor_id' => $supervisorId,
                            'supervisor' => $supervisor,
                            // Roles operativos del usuario en ESTA empresa (para
                            // filtrar el selector de supervisor a admins de la
                            // empresa correcta, no del rol global del usuario).
                            'roles' => $tenantRoleNames,
                            'role' => \App\Models\User::highestPriorityRole($tenantRoleNames),
                        ];
                    })->values(),  // ✅ Reset array keys after filter
                    // B3: los 4 conceptos del cliente (Pendientes/Gozadas/
                    // Truncas/Saldo) para la empresa activa de este listado.
                    // null cuando no hay una única empresa activa (ver arriba).
                    'vacation_balance' => isset($vacationBalances[$u->id]) ? [
                        'tenant_id' => $vacationBalances[$u->id]['tenant_id'],
                        'pending' => $vacationBalances[$u->id]['pending'],
                        'taken' => $vacationBalances[$u->id]['taken'],
                        'truncated' => $vacationBalances[$u->id]['truncated'],
                        'balance' => $vacationBalances[$u->id]['balance'],
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

    // ... (store method is fine, uses service)

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
        $user = User::with(['roles', 'tenants', 'tenantRoles.role'])->findOrFail($id);

        // Verificar acceso. Excepción de solo-lectura: aunque canManageUser()
        // bloquea la auto-administración (ver Decisión C1), el propio detalle
        // debe seguir siendo visible para no romper deep-links al perfil
        // propio (p. ej. desde notificaciones o el historial de auditoría).
        $currentUser = $request->user();
        if (!$this->userService->canManageUser($currentUser, $user) && $currentUser->id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return new UserResource($user);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Crear usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Usuario creado"),
     *     @OA\Response(response=422, description="Errores de validación")
     * )
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $user = $this->userService->createUser($request->validated());

            $this->auditService->logUserCreated($user->id, $user->only(self::AUDITED_FIELDS));

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => new UserResource($user),
            ], 201);
        } catch (UserCreationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);

        // Verificar acceso (Decisión C1: jerarquía de roles, no solo
        // solapamiento de tenant — ver UserService::canManageUser). A
        // diferencia de show(), aquí NO hay excepción de solo-lectura: nadie
        // se edita a sí mismo desde Gestión de Usuarios.
        $currentUser = $request->user();
        if (!$this->userService->canManageUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validated();

        // Solo el rol root puede cambiar el correo de un usuario
        if (
            array_key_exists('email', $validated)
            && $validated['email'] !== $user->email
            && !$currentUser->isRoot()
        ) {
            return response()->json([
                'message' => 'Solo el rol root puede cambiar el correo de un usuario.',
            ], 403);
        }

        $oldData = $user->only(self::AUDITED_FIELDS);

        $updatedUser = $this->userService->updateUser($user, $validated);

        $this->auditService->logUserUpdated(
            $updatedUser->id,
            $oldData,
            $updatedUser->only(self::AUDITED_FIELDS)
        );

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => new UserResource($updatedUser),
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
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Solo root puede eliminar usuarios (Matriz de Accesos: 'users.delete').
        $this->authorize('users.delete');

        // Verificar acceso
        $currentUser = $request->user();

        // Nadie puede eliminarse a sí mismo. Es una regla sobre el par
        // (actor, objetivo), no sobre el rol, así que no cabe en una ability
        // booleana de la matriz y vive aquí.
        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        // Decisión C1: jerarquía de roles (ver UserService::canManageUser),
        // no solo solapamiento de tenant.
        if (!$this->userService->canManageUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $deletedData = $user->only(self::AUDITED_FIELDS);

        $user->delete();

        $this->auditService->logUserDeleted($user->id, $deletedData);

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/subordinates",
     *     tags={"Usuarios"},
     *     summary="Obtener subordinados del usuario actual o de un usuario específico",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="ID del tenant (opcional, filtra por tenant específico)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Lista de subordinados")
     * )
     */
    public function subordinates(Request $request, $id = null)
    {
        // Si se proporciona un ID, obtener ese usuario, sino usar el autenticado
        if ($id) {
            $user = User::findOrFail($id);

            // Verificar acceso. Misma excepción de solo-lectura que show():
            // este endpoint alimenta la sub-sección "Subordinados" del
            // detalle de usuario, así que quien puede ver el detalle (propio
            // o de alguien que administra) también debe poder ver esto.
            $currentUser = $request->user();
            if (!$this->userService->canManageUser($currentUser, $user) && $currentUser->id !== $user->id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        } else {
            $user = $request->user();
        }

        $tenantId = $request->query('tenant_id');

        if ($tenantId) {
            // Obtener subordinados de un tenant específico
            $subordinates = $user->subordinatesForTenant((int) $tenantId)->get();
        } else {
            // Obtener todos los subordinados (de todos los tenants)
            $subordinates = $user->subordinates()->get();
        }

        return response()->json([
            'data' => $subordinates->map(function ($subordinate) {
                return [
                    'id' => $subordinate->id,
                    'name' => $subordinate->name,
                    'last_name' => $subordinate->last_name,
                    'full_name' => $subordinate->full_name,
                    'email' => $subordinate->email,
                    'status' => $subordinate->status,
                    'role' => $subordinate->getCurrentRole(),
                    'roles' => $subordinate->getCurrentRoles(),
                    'tenant_id' => $subordinate->pivot->tenant_id ?? null,
                    'is_primary' => $subordinate->pivot->is_primary ?? false,
                ];
            }),
        ]);
    }
}
