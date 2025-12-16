<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo root y admin pueden actualizar usuarios
        return $this->user() && \in_array($this->user()->getCurrentRole(), ['root', 'admin'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // apiResource uses 'user' as the route parameter name, not 'id'
        $userId = $this->route('user');

        return [
            'name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'document_type' => 'nullable|string|in:dni,ruc,ce,passport',
            'document_text' => [
                'nullable',
                'string',
                Rule::unique('users', 'document_text')->ignore($userId),
            ],
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|required|exists:roles,id',
            'status' => 'nullable|string|in:active,inactive,pending',
            'tenant_id' => 'nullable|exists:tenants,id',
            'tenant_ids' => 'nullable|array',

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

            'tenant_ids.*' => 'exists:tenants,id',
            'primary_tenant_id' => 'nullable|exists:tenants,id',
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
            'immediate_supervisor_id.exists' => 'El supervisor seleccionado no existe',
            'role_id.required' => 'El rol es requerido',
            'role_id.exists' => 'El rol seleccionado no existe',
            'tenant_id.exists' => 'La empresa seleccionada no existe',
            'tenant_ids.array' => 'Las empresas deben ser un array',
            'tenant_ids.*.exists' => 'Una o más empresas seleccionadas no existen',
            'primary_tenant_id.exists' => 'La empresa primaria seleccionada no existe',
            'status.in' => 'El estado debe ser: active, inactive o pending',
        ];
    }
}
