<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateUserBatchDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: parte del flujo de carga masiva (previsualización de
        // datos editados); gateado con el mismo control de rol que las
        // demás rutas de user-batches.
        return $this->user() && in_array($this->user()->getCurrentRole(), ['root', 'admin', 'admin_tenant']);
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
            'users.*.rol' => 'nullable|string',
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
