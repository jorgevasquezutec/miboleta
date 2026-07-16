<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTenantRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'role_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuario de esta asignación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Empresa (tenant) de esta asignación
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Rol asignado
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
