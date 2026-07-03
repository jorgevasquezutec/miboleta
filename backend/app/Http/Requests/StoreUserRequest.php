<?php

namespace App\Http\Requests;

use App\Models\Role;
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
            'birth_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,pending',

            // Nivel superior: relevante para el alta de un usuario root (rol
            // global, sin empresas) y como fallback legacy de creación simple
            // en una sola empresa. Para usuarios operativos con roles
            // distintos por empresa, usar tenants_config.*.role_ids.
            'role_id' => 'nullable|exists:roles,id',
            'tenant_id' => 'nullable|exists:tenants,id',

            // Configuración avanzada de tenants, roles por empresa y supervisores
            'tenants_config' => 'nullable|array',
            'tenants_config.*.tenant_id' => 'required|exists:tenants,id',
            'tenants_config.*.role_ids' => 'nullable|array',
            'tenants_config.*.role_ids.*' => [
                'exists:roles,id',
                function ($attribute, $value, $fail) {
                    if (Role::find($value)?->name === 'root') {
                        $fail('No se puede asignar el rol root dentro de una empresa.');
                    }
                },
            ],
            'tenants_config.*.hire_date' => 'nullable|date',
            'tenants_config.*.vacation_balance_initial' => 'nullable|numeric|min:0',
            'tenants_config.*.supervisor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }
                    $supervisor = User::find($value);
                    if (!$supervisor) {
                        return;
                    }
                    // Recuperar el tenant_id del mismo item de tenants_config
                    // (misma posición del array) para validar el supervisor
                    // en el contexto de ESA empresa específica.
                    $tenantIdAttribute = preg_replace('/supervisor_id$/', 'tenant_id', $attribute);
                    $tenantId = $this->input($tenantIdAttribute);
                    if ($tenantId && !$supervisor->hasRoleInTenant('admin', $tenantId)) {
                        $fail('El jefe inmediato debe ser un usuario con rol administrador en esa empresa.');
                    }
                },
            ],
            'tenants_config.*.is_primary' => 'boolean',
        ];
    }

    /**
     * Validaciones adicionales que dependen de la combinación de campos.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->input('role_id');
            $isRoot = $roleId && Role::find($roleId)?->name === 'root';

            if ($isRoot) {
                if ($this->filled('tenant_id') || $this->filled('tenants_config')) {
                    $validator->errors()->add('role_id', 'Un usuario root no puede tener empresas asignadas.');
                }
                return;
            }

            if (!$this->filled('tenant_id') && !$this->filled('tenants_config')) {
                $validator->errors()->add('tenant_id', 'Debe indicar al menos una empresa (tenant_id o tenants_config).');
            }
        });
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
            'birth_date.date' => 'La fecha de nacimiento no es válida',
            'role_id.exists' => 'El rol seleccionado no existe',
            'tenant_id.exists' => 'La empresa seleccionada no existe',
            'tenants_config.*.tenant_id.required' => 'Cada empresa configurada requiere un tenant_id',
            'tenants_config.*.tenant_id.exists' => 'Una de las empresas configuradas no existe',
            'tenants_config.*.role_ids.*.exists' => 'Uno de los roles seleccionados no existe',
            'tenants_config.*.hire_date.date' => 'La fecha de inicio laboral no es válida',
            'tenants_config.*.vacation_balance_initial.numeric' => 'El saldo inicial de vacaciones debe ser numérico',
            'tenants_config.*.vacation_balance_initial.min' => 'El saldo inicial de vacaciones no puede ser negativo',
            'status.in' => 'El estado debe ser: active, inactive o pending',
        ];
    }
}
