<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'document_type',
        'document_text',
        'phone',
        'immediate_supervisor_id',
        'status',
        'last_login_at',
        'must_change_password',
        'password_changed_at',
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
        ];
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
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Jefe inmediato (supervisor)
     */
    public function immediateSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'immediate_supervisor_id');
    }

    /**
     * Subordinados (empleados que reportan a este usuario)
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'immediate_supervisor_id');
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
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Verificar si es usuario root
     */
    public function isRoot(): bool
    {
        return $this->hasRole('root');
    }

    /**
     * Verificar si es admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Verificar si es client
     */
    public function isClient(): bool
    {
        return $this->hasRole('client');
    }

    /**
     * Obtener el rol principal del usuario (el primero)
     */
    public function getCurrentRole(): ?string
    {
        return $this->roles()->first()?->name;
    }

    /**
     * Obtener roles actuales del usuario
     */
    public function getCurrentRoles(): array
    {
        return $this->roles()->pluck('name')->toArray();
    }

    /**
     * Verificar si tiene un permiso específico (considera todos sus roles)
     */
    public function hasPermission(string $permission): bool
    {
        $roles = $this->roles()->get();

        foreach ($roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
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
}
