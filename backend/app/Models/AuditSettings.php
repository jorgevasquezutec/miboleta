<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración del mantenedor de auditoría. Tabla singleton: guarda el
 * conjunto de acciones cuya captura está DESACTIVADA (disabled_actions).
 * Default = [] (todo activo). Las acciones AuditLog::ALWAYS_ON nunca se
 * desactivan, aunque aparecieran aquí (ver AuditService::isActionEnabled()).
 *
 * Úsese AuditSettings::current() para obtener/crear la única fila, mismo
 * patrón que PlatformSettings::current() / SignatureSettings::current().
 */
class AuditSettings extends Model
{
    protected $table = 'audit_settings';

    protected $fillable = [
        'disabled_actions',
        'updated_by',
    ];

    protected $casts = [
        'disabled_actions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['disabled_actions' => []]);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
