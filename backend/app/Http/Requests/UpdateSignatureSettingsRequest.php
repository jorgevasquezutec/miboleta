<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSignatureSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real (isRoot) se valida en
        // SignatureCertificateService, que lanza UnauthorizedAccessException
        // (consistente con TenantService::deleteTenant).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'signature_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_enabled.required' => 'El campo signature_enabled es requerido',
            'signature_enabled.boolean' => 'signature_enabled debe ser verdadero o falso',
        ];
    }
}
