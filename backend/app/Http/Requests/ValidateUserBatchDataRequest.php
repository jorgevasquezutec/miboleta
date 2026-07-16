<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class ValidateUserBatchDataRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: parte del flujo de carga masiva (previsualización de
        // datos editados); gateado con el mismo control de rol que las
        // demás rutas de user-batches.
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
            'users' => 'required|array|min:1',
            'users.*.nombre' => 'nullable|string',
            'users.*.apellido' => 'nullable|string',
            'users.*.email' => 'nullable|string',
            'users.*.tipo_documento' => 'nullable|string',
            'users.*.numero_documento' => 'nullable|string',
            'users.*.estado' => 'nullable|string',
            'users.*.telefono' => 'nullable|string',
            // P1: birth_date debe declararse aquí para que FormRequest::validated()
            // no la descarte antes de llegar a BulkUserUploadService::validateData().
            'users.*.birth_date' => 'nullable|date',
            'users.*.organizaciones' => 'nullable|array',
            'users.*.row_number' => 'nullable|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'users.required' => 'Debe enviar al menos un usuario.',
            'users.min' => 'Debe enviar al menos un usuario.',
        ];
    }
}
