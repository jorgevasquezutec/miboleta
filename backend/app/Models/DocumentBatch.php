<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'type_id',
        'period',
        'original_filename',
        'total_files',
        'processed_files',
        'success_count',
        'replaced_count',
        'orphan_count',
        'error_count',
        'errors',
        'notify_employees',
        'notifications_sent',
        'requires_signature',
        'status',
        'started_at',
        'completed_at',
        'laravel_batch_id',
    ];

    protected $casts = [
        'errors' => 'array',
        'notify_employees' => 'boolean',
        'notifications_sent' => 'boolean',
        'requires_signature' => 'boolean',
        'total_files' => 'integer',
        'processed_files' => 'integer',
        'success_count' => 'integer',
        'replaced_count' => 'integer',
        'orphan_count' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this batch
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who uploaded this batch
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the document type
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'type_id');
    }

    /**
     * Get all documents in this batch
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'batch_id');
    }

    /**
     * Check if batch is still processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if batch is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors']);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_files === 0) {
            return 0;
        }
        return (int) (($this->processed_files / $this->total_files) * 100);
    }

    /**
     * Mark batch as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark batch as completed
     */
    public function markAsCompleted(): void
    {
        $status = $this->error_count > 0 ? 'completed_with_errors' : 'completed';
        $this->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark batch as failed
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $errors = $this->errors ?? [];
        if ($errorMessage) {
            $errors[] = ['file' => 'system', 'message' => $errorMessage];
        }

        $this->update([
            'status' => 'failed',
            'errors' => $errors,
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment processed count
     */
    public function incrementProcessed(bool $success = true, bool $replaced = false, bool $orphan = false): void
    {
        $this->increment('processed_files');

        if ($success) {
            $this->increment('success_count');
            if ($replaced) {
                $this->increment('replaced_count');
            }
            if ($orphan) {
                $this->increment('orphan_count');
            }
        } else {
            $this->increment('error_count');
        }
    }

    /**
     * Add error to batch
     */
    public function addError(string $file, string $message): void
    {
        $errors = $this->errors ?? [];
        $errors[] = ['file' => $file, 'message' => $message];
        $this->update(['errors' => $errors]);
    }
}
