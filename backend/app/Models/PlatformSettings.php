<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración GENERAL ÚNICA de la plataforma. Por ahora solo guarda la IP
 * pública del servidor (ítem 23), registrada manualmente por root para fines
 * informativos (whitelisting con terceros). No tiene ningún efecto en el
 * pipeline de red de la aplicación: es un valor de solo registro/consulta.
 *
 * Es una tabla singleton: siempre existe (o se crea de forma perezosa) una
 * única fila. Úsese PlatformSettings::current() para obtenerla/crearla en
 * vez de instanciar o consultar el modelo directamente (mismo patrón que
 * SignatureSettings::current()).
 */
class PlatformSettings extends Model
{
    protected $table = 'platform_settings';

    protected $fillable = [
        'public_ip',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene la única fila de configuración, creándola con valores por
     * defecto si todavía no existe. Patrón singleton simple (igual que
     * SignatureSettings::current()): como la tabla solo se escribe desde el
     * endpoint root (muy baja frecuencia), un firstOrCreate sin condiciones
     * es suficiente para garantizar una única fila en la práctica.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * Usuario root que actualizó por última vez la configuración.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
