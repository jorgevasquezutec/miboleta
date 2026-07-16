<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class DownloadUserBatchTemplateRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: parte del flujo de carga masiva (descarga de template
        // personalizado por organización); gateado con el mismo control de
        // rol que las demás rutas de user-batches.
        // Matriz: 'users.bulk_upload' = root, admin_tenant. 'admin' ya no puede
        // (la matriz no se lo concede); el rol se resuelve en la empresa ACTIVA.
        return $this->allowsAbility('users.bulk_upload');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_organizations' => 'required|integer|min:1|max:5',
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'integer|exists:tenants,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'max_organizations.required' => 'El número máximo de organizaciones es requerido.',
            'max_organizations.min' => 'Debe permitir al menos 1 organización.',
            'max_organizations.max' => 'El máximo de organizaciones permitido es 5.',
            'organization_ids.*.exists' => 'Una de las organizaciones seleccionadas no existe.',
        ];
    }
}
