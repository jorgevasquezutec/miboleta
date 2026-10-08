<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Carga del certificado de firma PROPIO de una empresa. Solo root
 * ('platform.manage'); la autorización corre ANTES de validar, así que un
 * admin recibe 403 sea cual sea el payload.
 */
class StoreTenantSignatureCertificateRequest extends FormRequest
{
    use ResolvesActiveRole;

    public function authorize(): bool
    {
        return $this->allowsAbility('platform.manage');
    }

    public function rules(): array
    {
        return [
            // Igual que StoreSignatureCertificateRequest: la extensión se
            // valida con un closure porque el MIME de .pfx/.p12 no es fiable.
            'certificate' => [
                'required',
                'file',
                'max:10240', // 10MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());

                    if (!in_array($extension, ['pfx', 'p12'], true)) {
                        $fail('El certificado debe ser un archivo .pfx o .p12.');
                    }
                },
            ],
            'password' => ['required', 'string', 'max:255'],
            // TSA propia (opcional); sin ella se usa la global. La vista previa la ignora.
            'tsa_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.required' => 'El certificado es requerido',
            'certificate.file' => 'Debe ser un archivo válido',
            'certificate.max' => 'El certificado no puede exceder 10MB',
            'password.required' => 'La contraseña del certificado es requerida',
            'tsa_url.url' => 'La URL del servicio de sello de tiempo (TSA) no es válida',
            'tsa_url.max' => 'La URL del servicio de sello de tiempo (TSA) no puede exceder 255 caracteres',
        ];
    }

    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'No autorizado. Solo el administrador de plataforma puede gestionar el certificado de firma.'
        );
    }
}
