<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'is_primary',
        'hire_date',
        'vacation_balance_initial',
        'department',
        'position',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'hire_date' => 'date',
        'vacation_balance_initial' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Usuario de esta relación
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tenant de esta relación
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
