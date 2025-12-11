<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'user_id',
        'code_hash',
        'attempts',
        'expires_at',
        'last_attempt_at',
        'used',
        'created_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Configuración
    public const CODE_LENGTH = 6;
    public const EXPIRY_MINUTES = 5;
    public const MAX_ATTEMPTS = 3;
    public const COOLDOWN_SECONDS = 60;

    /**
     * Get the document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if max attempts reached
     */
    public function hasMaxAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Check if cooldown is active (less than 60s since last attempt)
     */
    public function isInCooldown(): bool
    {
        if (!$this->last_attempt_at) {
            return false;
        }
        return $this->last_attempt_at->addSeconds(self::COOLDOWN_SECONDS)->isFuture();
    }

    /**
     * Get remaining cooldown seconds
     */
    public function getCooldownRemainingAttribute(): int
    {
        if (!$this->last_attempt_at) {
            return 0;
        }
        $remaining = $this->last_attempt_at->addSeconds(self::COOLDOWN_SECONDS)->diffInSeconds(now(), false);
        return max(0, -$remaining);
    }

    /**
     * Verify the code
     */
    public function verifyCode(string $code): bool
    {
        return hash_equals($this->code_hash, hash('sha256', $code));
    }

    /**
     * Increment attempts
     */
    public function incrementAttempts(): void
    {
        $this->update([
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * Mark as used
     */
    public function markAsUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Generate a new code
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new signature code for a document
     */
    public static function createForDocument(Document $document, User $user): array
    {
        // Invalidar códigos anteriores
        self::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('used', false)
            ->update(['used' => true]);

        $code = self::generateCode();

        $signatureCode = self::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'created_at' => now(),
        ]);

        return [
            'code' => $code,
            'signatureCode' => $signatureCode,
        ];
    }
}
