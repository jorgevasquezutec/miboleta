<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'La ruta del archivo es requerida',
            'path.string' => 'La ruta debe ser una cadena de texto',
        ];
    }
}
