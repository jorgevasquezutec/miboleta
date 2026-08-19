<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'impersonator_id',
        'token',
        'expires_at',
        'ip_address',
        'user_agent',
        'last_used_at',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Genera un nuevo refresh token
     */
    public static function generate(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $impersonatorId = null
    ): self {
        return self::create([
            'user_id' => $user->id,
            // Solo con valor cuando la sesión nace de una impersonación (ver
            // AuthService::impersonate). Es lo que permite que el refresh
            // conserve la marca sin depender de la cookie access_token, que
            // para entonces el navegador ya descartó.
            'impersonator_id' => $impersonatorId,
            'token' => hash('sha256', Str::random(60)),
            'expires_at' => Carbon::now()->addDays(30),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Verifica si el token es válido
     */
    public function isValid(): bool
    {
        return !$this->is_revoked 
            && $this->expires_at->isFuture();
    }

    /**
     * Revoca el token
     */
    public function revoke(): bool
    {
        return $this->update(['is_revoked' => true]);
    }

    /**
     * Actualiza el último uso del token
     */
    public function updateLastUsed(): bool
    {
        return $this->update(['last_used_at' => Carbon::now()]);
    }

    /**
     * Limpia tokens expirados o revocados
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', Carbon::now())
            ->orWhere('is_revoked', true)
            ->delete();
    }

    /**
     * Revoca todos los tokens de un usuario
     */
    public static function revokeAllForUser(int $userId): int
    {
        return self::where('user_id', $userId)
            ->update(['is_revoked' => true]);
    }
}

