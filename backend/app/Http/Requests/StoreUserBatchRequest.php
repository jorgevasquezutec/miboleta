<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserBatchRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: la carga masiva puede crear/actualizar usuarios con
        // cualquier rol operativo; sin este chequeo, CUALQUIER usuario
        // autenticado (p.ej. un 'client') podía disparar POST
        // /api/user-batches.
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
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'send_welcome_emails' => 'boolean',
            'update_existing' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Debe seleccionar un archivo Excel.',
            'file.file' => 'El archivo subido no es válido.',
            'file.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls).',
            'file.max' => 'El archivo no puede superar los 10MB.',
        ];
    }
}
