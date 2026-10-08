<?php

namespace App\Models;

use App\Models\Scopes\TenantFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // ✅ Aplicar filtro automático de tenant
        static::addGlobalScope(new TenantFilterScope);
    }


    protected $fillable = [
        'tenant_id',
        'user_id',
        'batch_id',
        'doc_type_id',
        'employee_document_number',
        'period',
        'file_path',
        'file_size',
        'original_name',
        'status',
        'uploaded_by',
        'requires_signature',
        'signature',
        'signed_at',
        'digital_signature',
        'digitally_signed_at',
        'digital_signature_status',
        'digital_signature_error',
        'original_file_path',
        'original_has_conformity',
        'expires_at',
        'notified',
        'notified_at',
        'version',
    ];

    /**
     * Detalle interno del pipeline de re-firma: no sale en ninguna
     * serialización (DocumentResource ya lo omite; esto cubre los listados
     * que devuelven el modelo directo).
     */
    protected $hidden = [
        'original_file_path',
        'original_has_conformity',
    ];

    protected $casts = [
        'signature' => 'array',
        'digital_signature' => 'array',
        'digitally_signed_at' => 'datetime',
        'original_has_conformity' => 'boolean',
        'requires_signature' => 'boolean',
        'notified' => 'boolean',
        'file_size' => 'integer',
        'version' => 'integer',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this document
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user (employee) that owns this document
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the batch this document belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentBatch::class, 'batch_id');
    }

    /**
     * Get the document type
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'doc_type_id');
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope for orphan documents
     */
    public function scopeOrphan($query)
    {
        return $query->where('status', 'orphan');
    }

    /**
     * Scope for pending signature
     */
    public function scopePendingSignature($query)
    {
        return $query->where('requires_signature', true)
            ->where('status', 'pending');
    }

    /**
     * Scope for signed documents
     */
    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    /**
     * Scope para documentos con firma digital (PAdES) de la empresa.
     */
    public function scopeDigitallySigned($query)
    {
        return $query->whereNotNull('digital_signature');
    }

    /**
     * Scope for documents by period
     */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Check if document is orphan
     */
    public function isOrphan(): bool
    {
        return $this->status === 'orphan';
    }

    /**
     * Check if document is signed
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    /**
     * Check if document is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast() && $this->status === 'pending';
    }

    /**
     * Check if document needs signature
     */
    public function needsSignature(): bool
    {
        return $this->requires_signature && $this->status === 'pending';
    }

    /**
     * Sign the document
     */
    public function sign(array $signatureData): void
    {
        $this->update([
            'status' => 'signed',
            'signature' => $signatureData,
            'signed_at' => now(),
        ]);
    }

    /**
     * ¿Tiene firma digital (PAdES) de la empresa aplicada? Es independiente
     * de la conformidad del trabajador (isSigned()).
     */
    public function hasDigitalSignature(): bool
    {
        return $this->digital_signature !== null;
    }

    /**
     * ¿El PDF firmado digitalmente que se sirve ya incluye el nombre del
     * trabajador (su conformidad)?
     */
    public function digitalSignatureIncludesConformity(): bool
    {
        return (bool) ($this->digital_signature['includes_conformity'] ?? false);
    }

    /**
     * Guarda la metadata de la firma PAdES de la empresa. Toca SOLO las
     * columnas digital_*: nunca `status`, `signature` ni `signed_at`, que son
     * de la conformidad del trabajador.
     */
    public function applyDigitalSignature(array $meta): void
    {
        $this->update([
            'digital_signature' => $meta,
            'digitally_signed_at' => now(),
        ]);
    }

    /**
     * Ruta relativa (disco 'documents') de la base de re-firma: la revisión 0
     * normalizada a PDF/A. Vive junto al archivo, en `.originals/`.
     */
    public function originalStoragePath(): string
    {
        $dir = dirname($this->file_path);

        return ($dir === '.' ? '' : $dir . '/') . '.originals/'
            . pathinfo($this->file_path, PATHINFO_FILENAME) . '.pdfa.pdf';
    }

    /**
     * Assign an orphan document to a user
     */
    public function assignToUser(User $user): void
    {
        $newStatus = $this->requires_signature ? 'pending' : 'pending';

        $this->update([
            'user_id' => $user->id,
            'status' => $newStatus,
        ]);
    }

    /**
     * Mark as notified
     */
    public function markAsNotified(): void
    {
        $this->update([
            'notified' => true,
            'notified_at' => now(),
        ]);
    }

    /**
     * Get the download URL
     */
    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return route('documents.download', $this->id);
    }

    /**
     * Get file size formatted
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Check if file exists
     */
    public function fileExists(): bool
    {
        return Storage::disk('documents')->exists($this->file_path);
    }
}
