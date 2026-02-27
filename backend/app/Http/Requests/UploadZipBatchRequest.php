<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadZipBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo root y admin pueden subir lotes ZIP
        return $this->user() && \in_array($this->user()->getCurrentRole(), ['root', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:zip|max:102400', // 100MB max
            'type_id' => 'required|exists:document_types,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/', // YYYY-MM format
            'tenant_id' => 'required|exists:tenants,id',
            'notify_employees' => 'boolean',
            'requires_signature' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido',
            'file.file' => 'Debe ser un archivo válido',
            'file.mimes' => 'El archivo debe ser un ZIP',
            'file.max' => 'El archivo no puede exceder 100MB',
            'type_id.required' => 'El tipo de documento es requerido',
            'type_id.exists' => 'El tipo de documento seleccionado no existe',
            'tenant_id.required' => 'El tenant es requerido',
            'tenant_id.exists' => 'El tenant seleccionado no existe',
            'period.required' => 'El período es requerido',
            'period.regex' => 'El período debe tener el formato YYYY-MM',
            'notify_employees.boolean' => 'El campo debe ser verdadero o falso',
            'requires_signature.boolean' => 'El campo debe ser verdadero o falso',
        ];
    }
}
