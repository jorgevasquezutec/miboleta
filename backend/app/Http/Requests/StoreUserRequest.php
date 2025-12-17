<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo root y admin pueden crear usuarios
        return $this->user() && in_array($this->user()->getCurrentRole(), ['root', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'document_type' => 'nullable|string|in:dni,ruc,ce,passport',
            'document_text' => 'nullable|string|unique:users,document_text',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'tenant_id' => 'required|exists:tenants,id', // Tenant principal inicial
            'status' => 'nullable|string|in:active,inactive,pending',

            // Configuración avanzada de tenants y supervisores
            'tenants_config' => 'nullable|array',
            'tenants_config.*.tenant_id' => 'required|exists:tenants,id',
            'tenants_config.*.supervisor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $supervisor = User::find($value);
                        if ($supervisor && !$supervisor->hasRole('admin')) {
                            $fail('El jefe inmediato debe ser un usuario con rol administrador.');
                        }
                    }
                },
            ],
            'tenants_config.*.is_primary' => 'boolean',
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
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser una dirección válida',
            'email.unique' => 'Este email ya está registrado',
            'document_type.in' => 'El tipo de documento debe ser: dni, ruc, ce o passport',
            'document_text.unique' => 'Este número de documento ya está registrado',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'role_id.required' => 'El rol es requerido',
            'role_id.exists' => 'El rol seleccionado no existe',
            'tenant_id.required' => 'La empresa es requerida',
            'tenant_id.exists' => 'La empresa seleccionada no existe',
            'status.in' => 'El estado debe ser: active, inactive o pending',
        ];
    }
}
