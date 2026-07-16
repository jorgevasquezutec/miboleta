<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real (isRoot) se valida en
        // PlatformSettingsService, que lanza UnauthorizedAccessException
        // (consistente con UpdateSignatureSettingsRequest).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Regla nativa "ip" de Laravel: valida IPv4 o IPv6.
            'public_ip' => ['nullable', 'ip'],
            // SMTP global de la plataforma (mailer por defecto administrable).
            // Todo nullable: si no se configura, se usa el mailer de .env
            // (ver TenantMailerService). Mismas reglas que StoreTenantRequest.
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer|between:1,65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'public_ip.ip' => 'La IP pública debe ser una dirección IPv4 o IPv6 válida',
            'mail_port.integer' => 'El puerto SMTP debe ser un número entero',
            'mail_port.between' => 'El puerto SMTP debe estar entre 1 y 65535',
            'mail_encryption.in' => 'La encriptación SMTP debe ser: tls o ssl',
            'mail_from_address.email' => 'El correo remitente debe ser una dirección de correo válida',
        ];
    }
}
