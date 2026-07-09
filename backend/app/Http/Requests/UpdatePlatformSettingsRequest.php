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
        ];
    }

    public function messages(): array
    {
        return [
            'public_ip.ip' => 'La IP pública debe ser una dirección IPv4 o IPv6 válida',
        ];
    }
}
