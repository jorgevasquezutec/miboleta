<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTenantLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authenticated users (middleware handles this)
    }

    public function rules(): array
    {
        return [
            'logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'El logo es requerido',
            'logo.image' => 'El archivo debe ser una imagen',
            'logo.mimes' => 'El logo debe ser de tipo: jpeg, jpg, png, gif o webp',
            'logo.max' => 'El logo no puede exceder 2MB',
        ];
    }
}
