<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración ÚNICA de la plataforma para la firma digital con
 * certificado (.pfx/.p12), usada para firmar documentos bajo DS-009-2011-TR.
 *
 * Es una tabla singleton: siempre existe (o se crea de forma perezosa) una
 * única fila. Úsese SignatureSettings::current() para obtenerla/crearla en
 * vez de instanciar o consultar el modelo directamente.
 *
 * Este modelo es agnóstico al firmador: solo persiste la configuración de
 * forma segura (password cifrada con el cast "encrypted", igual que
 * Tenant::mail_password). El binario del certificado NUNCA se expone via
 * API; solo se guarda su ruta dentro del disco privado "certificates".
 */
class SignatureSettings extends Model
{
    protected $table = 'signature_settings';

    protected $fillable = [
        'signature_enabled',
        'certificate_path',
        'certificate_password',
        'certificate_subject',
        'tsa_url',
        'uploaded_by',
        'uploaded_at',
    ];

    /**
     * Atributos que nunca deben serializarse (defensa adicional: el
     * controller/resource tampoco los expone, pero por si el modelo se
     * serializa directamente en algún log o respuesta futura).
     */
    protected $hidden = [
        'certificate_password',
    ];

    protected $casts = [
        'signature_enabled' => 'boolean',
        'certificate_password' => 'encrypted',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene la única fila de configuración, creándola con valores por
     * defecto si todavía no existe. Patrón singleton simple: como la tabla
     * solo se escribe desde los endpoints root (muy baja frecuencia), un
     * firstOrCreate sin condiciones es suficiente para garantizar una única
     * fila en la práctica.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'signature_enabled' => false,
        ]);
    }

    /**
     * Indica si hay un certificado cargado actualmente.
     */
    public function hasCertificate(): bool
    {
        return !empty($this->certificate_path);
    }

    /**
     * Usuario root que cargó el certificado vigente.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
