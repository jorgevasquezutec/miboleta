<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;


class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ruc',
        'business_name',
        'address',
        'phone',
        'logo_path',
        'status',
        'initial_employee_count',
        'labor_regime',
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
        'initial_employee_count' => 'integer',
        'mail_port' => 'integer',
        // Cifrado nativo de Laravel (usa APP_KEY). Se cifra/descifra de forma
        // transparente al leer/escribir el atributo; en BD queda como texto
        // cifrado en base64.
        'mail_password' => 'encrypted',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Attributes to append to model's array form
     */
    protected $appends = ['logo_url'];

    /**
     * Get the full URL for the logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        // Si ya es una URL completa (http/https), retornarla tal cual
        if (filter_var($this->logo_path, FILTER_VALIDATE_URL)) {
            return $this->logo_path;
        }

        // Si es un path de storage, convertirlo a URL absoluta con dominio
        return url(Storage::url($this->logo_path));
    }


    /**
     * Usuarios que pertenecen a este tenant
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenants')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Documentos del tenant
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Solicitudes de vacaciones del tenant
     */
    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    /**
     * Verificar si el tenant está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verificar si un usuario pertenece a este tenant
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Días de vacaciones que devenga un trabajador por año completo de
     * servicio en esta empresa, según su régimen laboral (D.Leg. 713 /
     * régimen MYPE): 30 días en régimen general, 15 días en MYPE
     * (micro o pequeña empresa).
     */
    public function vacationDaysPerYear(): int
    {
        return match ($this->labor_regime) {
            'micro', 'pequena' => 15,
            default => 30,
        };
    }

    /**
     * Contadores de empleados (RP1-C), fuente única para los dos lugares que
     * antes calculaban la misma fórmula por separado (TenantService::
     * transformTenantForList, usado en index/show, y TenantResource, usado
     * en store/update) con el riesgo de que un cambio a la fórmula solo se
     * aplicara en uno de los dos.
     *
     * "La carga inicial es la primera, luego de eso ya todo es nuevo"
     * (confirmado por el cliente): initial_employee_count se fija una sola
     * vez al completar la primera carga masiva (ver UserBatch::
     * syncInitialEmployeeCounts) y todo lo posterior — alta manual o cargas
     * siguientes — cuenta como "subsequent".
     *
     * @return array{current_employee_count:int, initial_employee_count:int, subsequent_employee_count:int}
     */
    public function employeeCounts(): array
    {
        $current = $this->users()->count();
        $initial = (int) ($this->initial_employee_count ?? 0);

        return [
            'current_employee_count' => $current,
            'initial_employee_count' => $initial,
            'subsequent_employee_count' => max(0, $current - $initial),
        ];
    }

    /**
     * Determina si la empresa tiene su propio servidor SMTP configurado.
     * Requiere al menos host y remitente; el resto de campos (usuario,
     * password, puerto, encriptación) son opcionales según el servidor.
     * Ver TenantMailerService::resolveMailer(), que usa este helper para
     * decidir entre el mailer propio del tenant y el de la plataforma.
     */
    public function hasCustomMailer(): bool
    {
        return !empty($this->mail_host) && !empty($this->mail_from_address);
    }
}
