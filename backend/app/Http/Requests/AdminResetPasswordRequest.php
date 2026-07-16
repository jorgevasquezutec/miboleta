<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class AdminResetPasswordRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Matriz: 'users.reset_password' = root, admin_tenant. Cambia respecto
        // del hardcode anterior ['root','admin']: 'admin' ya no puede resetear
        // contraseñas y 'admin_tenant' sí.
        return $this->allowsAbility('users.reset_password');
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
