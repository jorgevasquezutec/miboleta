<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Roles de supervisión válidos: quien figure como "jefe inmediato" de un
     * usuario en una empresa debe tener uno de estos roles EN ESA empresa
     * (ver closure de tenants_config.*.supervisor_id más abajo).
     */
    private const VALID_SUPERVISOR_ROLES = ['admin', 'admin_tenant', 'aprobador'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Alcanzabilidad del endpoint: basta con poder crear ALGÚN rol.
        // Matriz: create_any_role = [root], create_limited_role = [root,
        // admin_tenant]. Cambia respecto del hardcode anterior: 'admin' ya no
        // puede crear usuarios.
        //
        // QUÉ rol puede asignar cada uno es validación de payload y vive en
        // rules() — no cabe en una ability booleana.
        return $this->allowsAbility('users.create_any_role')
            || $this->allowsAbility('users.create_limited_role');
    }

    /**
     * Roles operativos que $creator puede asignar dentro de la empresa
     * $tenantId, según el rol que el propio $creator tiene EN ESA empresa
     * (jerarquía RBAC: root > admin_tenant > admin > aprobador/client).
     * Root no tiene rol por empresa (es global), así que se resuelve aparte.
     *
     * @return list<string> Nombres de rol permitidos (vacío si no puede asignar ninguno).
     */
    private function assignableRoleNamesFor(User $creator, int $tenantId): array
    {
        if ($creator->isRoot()) {
            return ['admin_tenant', 'admin', 'aprobador', 'client'];
        }

        if ($creator->hasRoleInTenant('admin_tenant', $tenantId)) {
            return ['admin', 'aprobador', 'client'];
        }

        if ($creator->hasRoleInTenant('admin', $tenantId)) {
            return ['aprobador', 'client'];
        }

        return [];
    }

    /**
     * FIX I2: si el creador NO es root, exige que tenga rol admin o
     * admin_tenant EN el tenant destino antes de permitir adjuntar (crear)
     * un usuario ahí. Sin este chequeo, un admin/admin_tenant de la Empresa
     * A podía enviar el tenant_id de una Empresa B ajena (vía el campo
     * legacy 'tenant_id' o vía tenants_config.*.tenant_id) y el usuario se
     * creaba igualmente adjunto a esa empresa ajena.
     */
    private function failIfCreatorCannotManageTenant($tenantId, $fail): void
    {
        if (!$tenantId) {
            return;
        }

        $creator = $this->user();
        if (!$creator || $creator->isRoot()) {
            return;
        }

        if (
            !$creator->hasRoleInTenant('admin', (int) $tenantId)
            && !$creator->hasRoleInTenant('admin_tenant', (int) $tenantId)
        ) {
            $fail('No tienes permisos para asignar usuarios a esa empresa.');
        }
    }

    /**
     * FIX B1-update: conjunto de tenants donde el role_id top-level
     * (fallback legacy) realmente se aplicará. Si viene tenant_id, es ese.
     * Si viene tenants_config, es el tenant_id de CADA item que NO trae
     * su propio role_ids (ver UserService::assignTenantsWithConfig: el
     * fallbackRoleId solo se usa por-item cuando ese item no especifica
     * role_ids propios). Antes solo se validaba el fallback contra
     * tenants_config.0.tenant_id, así que un admin_tenant podía enviar
     * varias organizaciones en tenants_config y colar un role_id elevado
     * (p.ej. admin_tenant) como fallback para la 2da/3ra organización sin
     * que el chequeo de jerarquía lo detectara.
     *
     * @return list<int>
     */
    private function resolveFallbackRoleTenantIds(): array
    {
        if ($this->filled('tenant_id')) {
            return [(int) $this->input('tenant_id')];
        }

        $tenantsConfig = (array) $this->input('tenants_config', []);
        if (empty($tenantsConfig)) {
            return [];
        }

        $tenantIds = [];
        foreach ($tenantsConfig as $item) {
            if (!is_array($item) || !isset($item['tenant_id'])) {
                continue;
            }

            // El fallback role_id top-level solo se usa para los items que
            // NO traen su propio role_ids.
            if (empty($item['role_ids'])) {
                $tenantIds[] = (int) $item['tenant_id'];
            }
        }

        return $tenantIds;
    }

    /**
     * Verificar si $supervisor tiene alguno de los roles válidos de
     * supervisión (VALID_SUPERVISOR_ROLES) dentro de $tenantId.
     */
    private function supervisorRoleIsValid(User $supervisor, $tenantId): bool
    {
        foreach (self::VALID_SUPERVISOR_ROLES as $role) {
            if ($supervisor->hasRoleInTenant($role, $tenantId)) {
                return true;
            }
        }

        return false;
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
            'tenant_id' => [
                'nullable',
                'exists:tenants,id',
                function ($attribute, $value, $fail) {
                    $this->failIfCreatorCannotManageTenant($value, $fail);
                },
            ],

            // Configuración avanzada de tenants, roles por empresa y supervisores
            'tenants_config' => 'nullable|array',
            'tenants_config.*.tenant_id' => [
                'required',
                'exists:tenants,id',
                function ($attribute, $value, $fail) {
                    $this->failIfCreatorCannotManageTenant($value, $fail);
                },
            ],
            'tenants_config.*.role_ids' => 'nullable|array',
            'tenants_config.*.role_ids.*' => [
                'exists:roles,id',
                function ($attribute, $value, $fail) {
                    $roleName = Role::find($value)?->name;

                    if ($roleName === 'root') {
                        $fail('No se puede asignar el rol root dentro de una empresa.');
                        return;
                    }

                    $creator = $this->user();
                    if (!$creator || $creator->isRoot()) {
                        // Root (o caso defensivo sin usuario resuelto) puede
                        // asignar cualquier rol operativo, ya validado arriba.
                        return;
                    }

                    // tenants_config.{n}.role_ids.{m} -> tenants_config.{n}.tenant_id
                    // (misma posición del array, igual que en supervisor_id más abajo).
                    $tenantIdAttribute = preg_replace('/role_ids\.\d+$/', 'tenant_id', $attribute);
                    $tenantId = $this->input($tenantIdAttribute);

                    if (!$tenantId) {
                        return;
                    }

                    $allowedRoleNames = $this->assignableRoleNamesFor($creator, (int) $tenantId);
                    if (!in_array($roleName, $allowedRoleNames, true)) {
                        $fail('No tienes permisos para asignar el rol seleccionado en esa empresa.');
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
                    if ($tenantId && !$this->supervisorRoleIsValid($supervisor, $tenantId)) {
                        $fail('El jefe inmediato debe tener rol Admin Empleados, Admin Clientes o Aprobador Empleado en esa empresa.');
                    }
                },
            ],
            'tenants_config.*.is_primary' => 'boolean',
            'tenants_config.*.department' => 'nullable|string|max:255',
            'tenants_config.*.position' => 'nullable|string|max:255',
        ];
    }

    /**
     * Validaciones adicionales que dependen de la combinación de campos.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->input('role_id');
            $roleName = $roleId ? Role::find($roleId)?->name : null;
            $isRoot = $roleName === 'root';
            $creator = $this->user();

            if ($isRoot) {
                // FIX B1: solo root puede dar de alta a otro root. Antes,
                // un admin/admin_tenant podía enviar role_id = id del rol
                // 'root' y, mientras no llenara tenant_id/tenants_config,
                // el usuario se creaba igual como root (escalada directa).
                if ($creator && !$creator->isRoot()) {
                    $validator->errors()->add('role_id', 'No tienes permisos para asignar el rol root.');
                    return;
                }

                if ($this->filled('tenant_id') || $this->filled('tenants_config')) {
                    $validator->errors()->add('role_id', 'Un usuario root no puede tener empresas asignadas.');
                }
                return;
            }

            if (!$this->filled('tenant_id') && !$this->filled('tenants_config')) {
                $validator->errors()->add('tenant_id', 'Debe indicar al menos una empresa (tenant_id o tenants_config).');
            }

            // FIX B1: el role_id top-level (fallback legacy de creación
            // simple en una sola empresa) también debe respetar la
            // jerarquía de assignableRoleNamesFor cuando el creador no es
            // root. Antes solo tenants_config.*.role_ids.* estaba
            // protegido; un admin podía usar este campo top-level para
            // asignar directamente un rol que no le correspondía
            // (p.ej. admin_tenant) sin pasar por ese chequeo.
            //
            // FIX B1-update: se valida contra CADA tenant donde el
            // fallback realmente se aplicaría (resolveFallbackRoleTenantIds),
            // no solo contra tenants_config.0 (ver doc del helper).
            if ($roleId && $roleName && $creator && !$creator->isRoot()) {
                foreach ($this->resolveFallbackRoleTenantIds() as $tenantIdDestino) {
                    $allowedRoleNames = $this->assignableRoleNamesFor($creator, $tenantIdDestino);
                    if (!in_array($roleName, $allowedRoleNames, true)) {
                        $validator->errors()->add('role_id', 'No tienes permisos para asignar ese rol.');
                        break;
                    }
                }
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
