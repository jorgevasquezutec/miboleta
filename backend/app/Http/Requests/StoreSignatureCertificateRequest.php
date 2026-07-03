<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignatureCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real (isRoot) se valida en
        // SignatureCertificateService, que lanza UnauthorizedAccessException
        // (consistente con TenantService::deleteTenant). Aquí solo exigimos
        // usuario autenticado; el middleware auth:sanctum ya lo garantiza.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // No usamos la regla "mimes" porque el tipo MIME de .pfx/.p12
            // no siempre se detecta de forma confiable; validamos la
            // extensión con un closure y reforzamos en el servicio.
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
            'password' => ['required', 'string', 'min:1', 'max:255'],
            'tsa_url' => ['nullable', 'url', 'max:500'],
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
        ];
    }
}
