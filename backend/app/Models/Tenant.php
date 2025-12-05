<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ruc',
        'business_name',
        'address',
        'phone',
        'logo_path',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Usuarios que pertenecen a este tenant
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenants')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Documentos del tenant
     */
    // public function documents(): HasMany
    // {
    //     return $this->hasMany(Document::class);
    // }

    /**
     * Solicitudes de vacaciones del tenant
     */
    // public function vacationRequests(): HasMany
    // {
    //     return $this->hasMany(VacationRequest::class);
    // }

    /**
     * Verificar si el tenant está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verificar si un usuario pertenece a este tenant
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }
}
