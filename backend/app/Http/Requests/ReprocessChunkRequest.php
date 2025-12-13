<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReprocessChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && \in_array($this->user()->getCurrentRole(), ['root', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:zip|max:102400',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es requerido',
            'file.file' => 'Debe ser un archivo válido',
            'file.mimes' => 'El archivo debe ser un ZIP',
            'file.max' => 'El archivo no puede exceder 100MB',
        ];
    }
}
