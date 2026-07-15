<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'permissions',
        'guard_name',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Usuarios que tienen este rol
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('granted_by', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Verificar si es el rol root
     */
    public function isRoot(): bool
    {
        return $this->name === 'root';
    }

    /**
     * Verificar si es el rol admin
     */
    public function isAdmin(): bool
    {
        return $this->name === 'admin';
    }

    /**
     * Verificar si es el rol client
     */
    public function isClient(): bool
    {
        return $this->name === 'client';
    }
}
