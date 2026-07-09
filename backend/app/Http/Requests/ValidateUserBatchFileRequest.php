<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateUserBatchFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: parte del flujo de carga masiva (previsualización de
        // archivo); gateado con el mismo control de rol que las demás
        // rutas de user-batches.
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
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
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
