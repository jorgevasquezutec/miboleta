<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo root y admin pueden resetear contraseñas
        return $this->user() && in_array($this->user()->getCurrentRole(), ['root', 'admin'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:generate,manual,force_change_only',
            'password' => 'required_if:action,manual|nullable|string|min:8',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'La acción es requerida',
            'action.in' => 'La acción debe ser: generate, manual o force_change_only',
            'password.required_if' => 'La contraseña es requerida cuando la acción es manual',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'must_change_password.boolean' => 'El campo debe ser verdadero o falso',
        ];
    }
}
