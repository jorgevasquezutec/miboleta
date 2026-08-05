<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * Convención de prioridad de roles operativos (no incluye 'root', que es
     * global y siempre gana). Se usa para elegir el rol "activo" cuando un
     * usuario tiene varios roles asignados en la misma empresa
     * (ver getCurrentRole/getRolesForTenant).
     *
     * @var list<string>
     */
    public const ROLE_PRIORITY = [
        'admin_tenant',
        'admin',
        'aprobador',
        'client',
    ];

    /**
     * Roles de EMPRESA que cuentan como "empleado" para efectos de conteo
     * (vs. cuentas de aplicación: 'root', global de la plataforma, y
     * 'admin_tenant', administrador de la empresa). La regla es POR ROL EN
     * CADA EMPRESA, con 'admin_tenant' DOMINANTE dentro de su empresa: quien
     * es admin_tenant de una empresa es cuenta de aplicación ahí aunque tenga
     * además un rol de esta lista (no cuenta como empleado ahí); si esa misma
     * persona es solo empleado en OTRA empresa, allá sí cuenta.
     *
     * Fuente única; ver Tenant::employeesQuery() (que la consume vía
     * whereIn contra user_tenant_roles) y su espejo en el frontend,
     * src/shared/constants/index.ts (ORG_EMPLOYEE_ROLES).
     *
     * @var list<string>
     */
    public const ORG_EMPLOYEE_ROLES = ['admin', 'client', 'aprobador'];

    /**
     * Dado un conjunto de nombres de rol, retorna el de mayor prioridad según
     * ROLE_PRIORITY. Un rol que no esté en la lista de prioridad se considera
     * de menor prioridad que cualquiera que sí esté, pero igual se devuelve si
     * es el único disponible.
     *
     * @param iterable<string|null> $roleNames
     */
    public static function highestPriorityRole(iterable $roleNames): ?string
    {
        $names = collect($roleNames)->filter()->unique()->values();

        if ($names->isEmpty()) {
            return null;
        }

        return $names->sort(function (string $a, string $b) {
            $posA = array_search($a, self::ROLE_PRIORITY, true);
            $posB = array_search($b, self::ROLE_PRIORITY, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;

            return $posA <=> $posB;
        })->first();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'avatar_url',
        'password',
        'document_type',
        'document_text',
        'phone',
        'birth_date',
        'status',
        'last_login_at',
        'must_change_password',
        'password_changed_at',
        'signature_terms_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'deleted_at' => 'datetime',
            'signature_terms_accepted_at' => 'datetime',
            'birth_date' => 'date',
        ];
    }

    /**
     * Attributes to append to model's array form
     */
    protected $appends = ['full_name'];

    /**
     * Get the full URL for the avatar
     */
    public function getAvatarUrlAttribute(): ?string
    {
        // Obtener el valor del atributo original
        $avatarPath = $this->attributes['avatar_url'] ?? null;

        if (!$avatarPath) {
            return null;
        }

        // Si ya es una URL completa (http/https), retornarla tal cual
        if (filter_var($avatarPath, FILTER_VALIDATE_URL)) {
            return $avatarPath;
        }

        // Si es un path de storage, convertirlo a URL absoluta con dominio
        return url(\Illuminate\Support\Facades\Storage::url($avatarPath));
    }

    /**
     * Roles del usuario
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Tenants a los que pertenece el usuario
     */
    /**
     * Tenants a los que pertenece el usuario
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants')
            ->withPivot(['is_primary', 'supervisor_id', 'hire_date', 'vacation_balance_initial', 'department', 'position'])
            ->withTimestamps();
    }

    /**
     * Asignaciones de rol por empresa del usuario (tabla pivote user_tenant_roles).
     *
     * A diferencia de roles() (global, vía user_roles, donde vive únicamente 'root'),
     * esta relación modela el híbrido: un usuario puede tener varios roles operativos
     * (admin_tenant, admin, client, aprobador) distintos en cada empresa.
     */
    public function tenantRoles(): HasMany
    {
        return $this->hasMany(UserTenantRole::class);
    }

    /**
     * Roles asignados al usuario dentro de una empresa específica.
     *
     * @return Collection<int, Role>
     */
    public function getRolesForTenant($tenantId): Collection
    {
        $roleIds = $this->tenantRoles()
            ->where('tenant_id', $tenantId)
            ->pluck('role_id');

        return Role::whereIn('id', $roleIds)->get();
    }

    /**
     * Verificar si el usuario tiene un rol específico dentro de una empresa.
     */
    public function hasRoleInTenant(string $roleName, $tenantId): bool
    {
        return $this->tenantRoles()
            ->where('tenant_id', $tenantId)
            ->whereHas('role', fn ($query) => $query->where('name', $roleName))
            ->exists();
    }

    /**
     * Obtener el supervisor para un tenant específico
     */
    public function getSupervisorForTenant(int $tenantId): ?User
    {
        $tenant = $this->tenants()->where('tenants.id', $tenantId)->first();
        if ($tenant && $tenant->pivot->supervisor_id) {
            return User::find($tenant->pivot->supervisor_id);
        }
        return null;
    }

    /**
     * Subordinados (empleados que reportan a este usuario en cualquier tenant)
     * Warning: This returns users who have this user as supervisor in ANY tenant
     */
    public function subordinates(): BelongsToMany
    {
        // Relación inversa many-to-many a través de la tabla pivote user_tenants
        // Queremos usuarios donde user_tenants.supervisor_id = $this->id
        return $this->belongsToMany(User::class, 'user_tenants', 'supervisor_id', 'user_id')
            ->withPivot(['is_primary', 'tenant_id'])
            ->withTimestamps();
    }
    
    /**
     * Get subordinates for a specific tenant
     */
    public function subordinatesForTenant(int $tenantId): BelongsToMany
    {
        return $this->subordinates()
            ->wherePivot('tenant_id', $tenantId);
    }

    /**
     * Refresh tokens del usuario
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Documentos del usuario
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Solicitudes de vacaciones del usuario
     */
    // public function vacationRequests(): HasMany
    // {
    //     return $this->hasMany(VacationRequest::class);
    // }

    /**
     * ¿El usuario tiene este rol GLOBAL (user_roles)?
     *
     * OJO: user_roles es la UNIÓN de los roles del usuario en todas sus
     * empresas (ver UserService::syncGlobalRoleFallback), así que preguntar
     * aquí por un rol operativo ('admin', 'client', …) responde "sí" aunque lo
     * tenga en OTRA empresa. NO usar para autorizar: para eso están
     * roleForTenant()/hasRoleInTenant()/hasAbility(), que resuelven contra una
     * empresa concreta. El único rol que tiene sentido consultar aquí es
     * 'root', que es global de plataforma por diseño (no tiene empresas).
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * ¿Es root? Único uso legítimo del rol global: root es de plataforma y no
     * pertenece a ninguna empresa, así que su rol no puede vivir en
     * user_tenant_roles.
     *
     * (Aquí vivían isAdmin() e isClient(); se eliminaron porque preguntaban por
     * roles operativos contra la unión global: ser admin en la empresa B daba
     * "true" al operar en la A. Su único llamador era TenantPolicy, que se
     * eliminó por esa misma fuga.)
     */
    public function isRoot(): bool
    {
        return $this->hasRole('root');
    }

    /**
     * Obtener el rol "actual" del usuario.
     *
     * Sin argumentos, mantiene el comportamiento actual (compatibilidad con
     * llamadas existentes): el primero de sus roles globales (user_roles).
     *
     * Si se indica $tenantId, resuelve el rol de mayor prioridad (ver
     * ROLE_PRIORITY/highestPriorityRole) entre los roles asignados en esa
     * empresa (user_tenant_roles). 'root' es global y siempre tiene prioridad.
     * Si el usuario no tiene ningún rol asignado en esa empresa, cae al
     * comportamiento por defecto descrito arriba.
     *
     * TODO(RP1-B): cuando el workstream de auth/services resuelva el tenant
     * activo (p. ej. header X-Tenant-Ids) de forma centralizada, los callers
     * deberían pasar ese $tenantId explícitamente en vez de depender del
     * comportamiento por defecto (primer rol global).
     */
    public function getCurrentRole(?int $tenantId = null): ?string
    {
        if ($this->isRoot()) {
            return 'root';
        }

        if ($tenantId !== null) {
            $roleName = self::highestPriorityRole($this->getRolesForTenant($tenantId)->pluck('name'));

            if ($roleName) {
                return $roleName;
            }
        }

        return $this->roles()->first()?->name;
    }

    /**
     * Rol EFECTIVO del usuario dentro de una empresa concreta, sin respaldo
     * global. Es la primitiva correcta para AUTORIZAR.
     *
     * Diferencia clave con getCurrentRole($tenantId): ese método, si el usuario
     * no tiene rol en la empresa indicada, cae al respaldo global
     * (`user_roles`, la UNIÓN de los roles del usuario en TODAS sus empresas,
     * resuelto con ->first() sin ORDER BY = no determinístico). Ese fallback es
     * una fuga de privilegios entre empresas: un admin en la empresa A podría
     * pasar checks operando en la B. Aquí NO hay fallback: sin rol en esa
     * empresa, el resultado es null (fail-closed).
     *
     * getCurrentRole() sigue existiendo para usos de DISPLAY (UserResource,
     * ProfileService, logs). No usarlo para autorizar.
     */
    public static function roleForTenant(?self $user, ?int $tenantId): ?string
    {
        if ($user === null) {
            return null;
        }

        // Root es global (plataforma): no depende de ninguna empresa.
        if ($user->isRoot()) {
            return 'root';
        }

        if ($tenantId === null) {
            return null;
        }

        return self::highestPriorityRole($user->getRolesForTenant($tenantId)->pluck('name'));
    }

    /**
     * ¿El usuario tiene la ability dentro de la empresa indicada?
     *
     * Lee config/access_matrix.php (fuente única de verdad). No llamar
     * directamente desde controllers/services: usar $user->can($ability, $tenantId)
     * o $this->authorize($ability, $tenantId) — los Gates se registran desde este
     * mismo config en AppServiceProvider::boot().
     */
    public function hasAbility(string $ability, ?int $tenantId = null): bool
    {
        // OJO: no usar config("access_matrix.{$ability}") — config() interpreta
        // los puntos como anidamiento, y las abilities los llevan en el nombre
        // ('tenants.manage'), así que buscaría ['access_matrix']['tenants']['manage'].
        // Hay que leer el array completo e indexar con la clave literal.
        $matrix = config('access_matrix', []);
        $allowedRoles = $matrix[$ability] ?? null;

        if (!is_array($allowedRoles)) {
            // Ability inexistente: fail-closed y reportar (nunca permitir en silencio).
            report(new \InvalidArgumentException("Unknown ability: {$ability}"));

            return false;
        }

        $role = self::roleForTenant($this, $tenantId);

        return $role !== null && in_array($role, $allowedRoles, true);
    }

    /**
     * Obtener roles actuales del usuario
     */
    public function getCurrentRoles(): array
    {
        return $this->roles()->pluck('name')->toArray();
    }

    /**
     * Obtener el tenant primario del usuario
     */
    public function primaryTenant(): ?Tenant
    {
        return $this->tenants()->wherePivot('is_primary', true)->first();
    }

    /**
     * Verificar si el usuario pertenece a un tenant específico
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenants()->where('tenants.id', $tenantId)->exists();
    }

    /**
     * Verificar si el usuario está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Obtener nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }

    /**
     * Filtra por coincidencia de NOMBRE COMPLETO: cada palabra de $search
     * debe aparecer en `name` o en `last_name` (no necesariamente en la
     * misma columna), así "Juan Pérez" encuentra a un usuario con
     * name=Juan, last_name=Pérez aunque ninguna columna por separado
     * contenga la cadena completa (el bug que este scope corrige: antes se
     * comparaba cada campo contra el término completo).
     *
     * Portable en sqlite (tests) y MySQL (producción) a propósito: solo usa
     * LIKE/AND/OR de la query builder, sin CONCAT (en MySQL, CONCAT con un
     * `last_name` NULL devuelve NULL y anularía el match) ni el operador
     * `||` (concatenación en sqlite, pero no en MySQL salvo con
     * PIPES_AS_CONCAT, que no es el modo por defecto).
     *
     * Reutilizado desde UserController::index, VacationService::
     * buildAllRequestsQuery y DocumentService::applyOptionalFilters — los
     * tres listados que antes repetían (mal) el mismo patrón.
     */
    public function scopeMatchingFullName(Builder $query, ?string $search): Builder
    {
        // $search es nullable a propósito: ConvertEmptyStringsToNull (activo
        // por defecto en Laravel) convierte un `?search=` vacío de la query
        // string en null, así que un call site que solo compruebe la
        // presencia del parámetro nos entrega null y con la firma `string`
        // esto era un TypeError 500 en vez de "no filtrar nada".
        $terms = preg_split('/\s+/', trim($search ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($terms)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($terms) {
            foreach ($terms as $term) {
                $q->where(function (Builder $termQuery) use ($term) {
                    $termQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            }
        });
    }
}
