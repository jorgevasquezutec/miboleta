<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'requires_signature',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active document types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Get all documents of this type
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'type_id');
    }

    /**
     * Get all batches of this type
     */
    public function batches(): HasMany
    {
        return $this->hasMany(DocumentBatch::class, 'type_id');
    }
}
