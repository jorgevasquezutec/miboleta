<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use App\Http\Requests\Concerns\ResolvesActiveRole;
use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        // Matriz: 'users.update' = root, admin_tenant. Cambia respecto del
        // hardcode anterior: 'admin' ya no puede editar usuarios.
        return $this->allowsAbility('users.update');
    }

    /**
     * Roles operativos que $creator puede asignar dentro de la empresa
     * $tenantId. Delega en UserService::assignableRoleNamesFor() — fuente
     * única compartida con StoreUserRequest (antes duplicado literal en
     * ambos archivos).
     *
     * @return list<string> Nombres de rol permitidos (vacío si no puede asignar ninguno).
     */
    private function assignableRoleNamesFor(User $creator, int $tenantId): array
    {
        return UserService::assignableRoleNamesFor($creator, $tenantId);
    }

    /**
     * FIX I2: si el creador NO es root, exige que tenga rol admin o
     * admin_tenant EN el tenant destino antes de permitir adjuntar/mantener
     * un usuario ahí. Sin este chequeo, un admin/admin_tenant de la Empresa
     * A podía enviar el tenant_id de una Empresa B ajena (vía el campo
     * legacy 'tenant_id'/'tenant_ids' o vía tenants_config.*.tenant_id) y el
     * usuario quedaba igualmente adjunto a esa empresa ajena.
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
     * Usuario OBJETIVO de esta actualización (el que se está editando),
     * resuelto y cacheado a partir del parámetro de ruta. Se usa en el
     * closure de tenants_config.*.role_ids.* (FIX I1) para eximir del
     * chequeo de jerarquía los roles que el objetivo YA tiene en ese tenant.
     */
    private ?User $cachedTargetUser = null;
    private bool $targetUserResolved = false;

    private function targetUser(): ?User
    {
        if (!$this->targetUserResolved) {
            $this->targetUserResolved = true;
            $userId = $this->route('user');
            $this->cachedTargetUser = $userId ? User::find($userId) : null;
        }

        return $this->cachedTargetUser;
    }

    /**
     * FIX B1-update: conjunto de tenants DESTINO donde se aplicará el
     * role_id top-level (fallback legacy) si la jerarquía lo permite.
     * Antes, el chequeo de withValidator solo miraba
     * tenant_id ?? tenants_config.0.tenant_id: no consideraba tenant_ids,
     * y si el payload no traía NINGÚN campo de tenant (p.ej. un PUT que
     * solo cambia role_id de un usuario ya existente), el chequeo se
     * saltaba por completo. UserService::updateUser (rama `elseif
     * ($roleChanged)`) aplica ese role_id a TODAS las empresas ACTUALES
     * del usuario objetivo cuando no viene ningún campo de tenant, así
     * que ese es el conjunto que hay que validar en ese caso.
     *
     * @return list<int>
     */
    private function resolveRoleFallbackTenantIds(): array
    {
        if ($this->filled('tenant_id')) {
            return [(int) $this->input('tenant_id')];
        }

        if ($this->filled('tenant_ids')) {
            return array_values(array_map('intval', (array) $this->input('tenant_ids')));
        }

        if ($this->filled('tenants_config')) {
            return array_values(array_filter(array_map(
                fn ($item) => (is_array($item) && isset($item['tenant_id'])) ? (int) $item['tenant_id'] : null,
                (array) $this->input('tenants_config')
            )));
        }

        $target = $this->targetUser();
        if ($target) {
            return $target->tenants()->pluck('tenants.id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
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
            'birth_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive,pending',

            // Nivel superior: relevante para transicionar a/desde root (rol
            // global, sin empresas) y como fallback legacy de asignación
            // simple en una sola empresa. Para usuarios operativos con roles
            // distintos por empresa, usar tenants_config.*.role_ids.
            'role_id' => 'sometimes|nullable|exists:roles,id',
            'tenant_id' => [
                'nullable',
                'exists:tenants,id',
                function ($attribute, $value, $fail) {
                    $this->failIfCreatorCannotManageTenant($value, $fail);
                },
            ],
            'tenant_ids' => 'nullable|array',

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

                    // FIX I1 (regresión): si el usuario OBJETIVO ya tiene
                    // este rol en este tenant, no lo volvemos a validar
                    // contra la jerarquía. El frontend reenvía los
                    // role_ids ACTUALES del usuario al editar (aunque solo
                    // se esté cambiando, p.ej., el teléfono), y antes de
                    // este fix eso producía un 422 para cualquier admin que
                    // editara a otro admin (o admin_tenant que editara a
                    // otro admin_tenant), porque ese rol ya asignado no
                    // está en assignableRoleNamesFor del editor. Solo los
                    // roles NUEVOS que se están agregando pasan por el
                    // chequeo de jerarquía.
                    $target = $this->targetUser();
                    if ($target && $roleName && $target->hasRoleInTenant($roleName, (int) $tenantId)) {
                        return;
                    }

                    $allowedRoleNames = $this->assignableRoleNamesFor($creator, (int) $tenantId);
                    if (!in_array($roleName, $allowedRoleNames, true)) {
                        $fail('No tienes permisos para asignar el rol seleccionado en esa empresa.');
                    }
                },
            ],
            // before_or_equal:today: sin este guard, un hire_date futuro (dato
            // mal tipeado) produce años de servicio negativos en
            // VacationBalanceService (ver guard defensivo ahí también).
            'tenants_config.*.hire_date' => 'nullable|date|before_or_equal:today',
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

            'tenant_ids.*' => [
                'exists:tenants,id',
                function ($attribute, $value, $fail) {
                    $this->failIfCreatorCannotManageTenant($value, $fail);
                },
            ],
            'primary_tenant_id' => 'nullable|exists:tenants,id',
        ];
    }

    /**
     * Validaciones adicionales que dependen de la combinación de campos.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $roleId = $this->input('role_id');
            if (!$roleId) {
                return;
            }

            $roleName = Role::find($roleId)?->name;
            $isRoot = $roleName === 'root';
            $creator = $this->user();

            if ($isRoot) {
                // FIX B1: solo root puede ascender a otro usuario a root.
                // Antes, un admin/admin_tenant podía enviar
                // role_id = id del rol 'root' sin tenant_id/tenant_ids/
                // tenants_config y la actualización lo convertía en root
                // igual (escalada directa).
                if ($creator && !$creator->isRoot()) {
                    $validator->errors()->add('role_id', 'No tienes permisos para asignar el rol root.');
                    return;
                }

                if ($this->filled('tenant_id') || $this->filled('tenant_ids') || $this->filled('tenants_config')) {
                    $validator->errors()->add('role_id', 'Un usuario root no puede tener empresas asignadas.');
                }
                return;
            }

            // FIX B1: el role_id top-level (fallback legacy) también debe
            // respetar la jerarquía de assignableRoleNamesFor cuando el
            // creador no es root; si no, un admin podría escalar el rol de
            // otro usuario a uno que no le corresponde asignar (p.ej.
            // admin_tenant) usando este campo en vez de
            // tenants_config.*.role_ids (que sí está validado).
            //
            // FIX B1-update: se valida contra CADA tenant destino
            // (resolveRoleFallbackTenantIds), no solo contra el primero.
            // Cuando el payload no trae ningún campo de tenant, el destino
            // real son los tenants ACTUALES del usuario objetivo, porque
            // ahí es donde UserService::updateUser terminará aplicando el
            // rol (rama `elseif ($roleChanged)`); sin este chequeo ese
            // caso se saltaba por completo (ver doc del helper).
            if ($roleName && $creator && !$creator->isRoot()) {
                foreach ($this->resolveRoleFallbackTenantIds() as $tenantIdDestino) {
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
            'tenant_ids.array' => 'Las empresas deben ser un array',
            'tenant_ids.*.exists' => 'Una o más empresas seleccionadas no existen',
            'tenants_config.*.tenant_id.required' => 'Cada empresa configurada requiere un tenant_id',
            'tenants_config.*.tenant_id.exists' => 'Una de las empresas configuradas no existe',
            'tenants_config.*.role_ids.*.exists' => 'Uno de los roles seleccionados no existe',
            'tenants_config.*.hire_date.date' => 'La fecha de inicio laboral no es válida',
            'tenants_config.*.hire_date.before_or_equal' => 'La fecha de inicio laboral no puede ser una fecha futura',
            'tenants_config.*.vacation_balance_initial.numeric' => 'El saldo inicial de vacaciones debe ser numérico',
            'tenants_config.*.vacation_balance_initial.min' => 'El saldo inicial de vacaciones no puede ser negativo',
            'primary_tenant_id.exists' => 'La empresa primaria seleccionada no existe',
            'status.in' => 'El estado debe ser: active, inactive o pending',
        ];
    }
}
