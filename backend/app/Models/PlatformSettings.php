<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración GENERAL ÚNICA de la plataforma. Guarda:
 *  - la IP pública del servidor (ítem 23), registrada manualmente por root
 *    para fines informativos (whitelisting con terceros); valor de solo
 *    registro/consulta, sin efecto en el pipeline de red, y
 *  - el servidor SMTP GLOBAL (campos mail_*), administrable por root desde el
 *    panel, usado como mailer por defecto cuando una empresa no tiene SMTP
 *    propio. Precedencia (ver TenantMailerService::resolveMailer()): SMTP de
 *    la empresa -> SMTP global (aquí) -> .env/config/mail.php.
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
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    /**
     * mail_password nunca debe salir en la representación array/JSON del
     * modelo (API responses, logs de Eloquent, etc.). Ver hasCustomMailer()
     * y TenantMailerService.
     */
    protected $hidden = [
        'mail_password',
    ];

    protected $casts = [
        'mail_port' => 'integer',
        // Cifrado nativo de Laravel (usa APP_KEY). Se cifra/descifra de forma
        // transparente al leer/escribir el atributo; en BD queda como texto
        // cifrado en base64.
        'mail_password' => 'encrypted',
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

    /**
     * Determina si la plataforma tiene un servidor SMTP global configurado.
     * Requiere al menos host y remitente; el resto de campos (usuario,
     * password, puerto, encriptación) son opcionales según el servidor.
     * Misma regla que Tenant::hasCustomMailer(); TenantMailerService lo usa
     * para decidir entre el SMTP global y el mailer por defecto de .env.
     */
    public function hasCustomMailer(): bool
    {
        return !empty($this->mail_host) && !empty($this->mail_from_address);
    }
}
