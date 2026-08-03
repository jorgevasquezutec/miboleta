<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Http\Requests\Concerns\ResolvesActiveRole;
use App\Services\BulkUserUploadService;
use Illuminate\Foundation\Http\FormRequest;

class UploadUserBatchDataRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // FIX B2.1: mismo control de rol que StoreUserBatchRequest; esta
        // ruta también dispara la creación/actualización masiva de
        // usuarios (con datos ya editados), así que no puede quedar
        // abierta a cualquier usuario autenticado.
        // Matriz: 'users.bulk_upload' = root, admin_tenant. 'admin' ya no puede
        // (la matriz no se lo concede); el rol se resuelve en la empresa ACTIVA.
        return $this->allowsAbility('users.bulk_upload');
    }

    /**
     * Normalizar los campos opcionales por organización antes de validar:
     * strings vacíos de fecha/saldo se tratan como ausentes (si no, la regla
     * 'date'/'numeric' fallaría para el caso común de dejarlos en blanco), y
     * 'roles' admite tanto array como string separado por coma.
     */
    protected function prepareForValidation(): void
    {
        $users = $this->input('users', []);

        if (!is_array($users)) {
            return;
        }

        foreach ($users as $i => $user) {
            // P1: fecha de nacimiento (campo de la fila, no de organización).
            // Mismo criterio que hire_date/vacation_balance_initial: '' se
            // trata como ausente para que la regla 'date' no falle con el
            // caso común de dejarla en blanco.
            if (is_array($user) && array_key_exists('birth_date', $user) && $user['birth_date'] === '') {
                $users[$i]['birth_date'] = null;
            }

            if (empty($user['organizaciones']) || !is_array($user['organizaciones'])) {
                continue;
            }

            foreach ($user['organizaciones'] as $j => $org) {
                if (!is_array($org)) {
                    continue;
                }

                if (array_key_exists('hire_date', $org) && $org['hire_date'] === '') {
                    $users[$i]['organizaciones'][$j]['hire_date'] = null;
                }

                if (array_key_exists('vacation_balance_initial', $org) && $org['vacation_balance_initial'] === '') {
                    $users[$i]['organizaciones'][$j]['vacation_balance_initial'] = null;
                }

                if (isset($org['roles']) && !is_array($org['roles'])) {
                    $users[$i]['organizaciones'][$j]['roles'] = array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) $org['roles'])
                    )));
                }
            }
        }

        $this->merge(['users' => $users]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Roles operativos por organización permitidos según quién sube el
        // grid [OBS-CLIENTE 2026-08]: root puede asignar 'admin_tenant';
        // cualquier otro actor (incluido admin_tenant) no. Ver
        // BulkUserUploadService::allowedOrgRolesFor, fuente única.
        $allowedOrgRoles = BulkUserUploadService::allowedOrgRolesFor($this->user());

        return [
            'users' => 'required|array|min:1',
            'users.*.nombre' => 'required|string',
            'users.*.apellido' => 'required|string',
            'users.*.email' => 'required|email',
            'users.*.tipo_documento' => 'required|in:dni,ce,passport,ruc',
            'users.*.numero_documento' => 'required|string',
            'users.*.estado' => 'required|in:active,inactive',
            'users.*.telefono' => 'nullable|string',
            // P1: fecha de nacimiento del usuario (campo de users, no de organización).
            'users.*.birth_date' => 'nullable|date',
            // Al menos una empresa por fila: el rol del usuario vive en la
            // empresa (user_tenant_roles), así que una fila sin empresa
            // crearía un usuario sin empresas y sin ningún rol.
            'users.*.organizaciones' => 'required|array|min:1',
            'users.*.organizaciones.*.ruc' => 'nullable',
            'users.*.organizaciones.*.supervisor_email' => 'nullable',
            // RP1-C: rol(es)/fecha de ingreso/saldo de vacaciones por organización.
            //
            // Los roles son requeridos y 'root' queda excluido a propósito:
            // es el único lugar donde la carga masiva asigna roles (no existe
            // una columna 'rol' de nivel de fila), y solo un root puede dar de
            // alta a otro root, jerarquía que esta vía no puede verificar por
            // fila. Los root se crean a mano desde el alta individual.
            // 'admin_tenant' quedó excluido para TODOS desde [OBS-CLIENTE
            // 2026-07]; [OBS-CLIENTE 2026-08] aclaró que root sí puede
            // asignarlo por esta vía (admin_tenant/admin siguen sin poder).
            // Fuente única: BulkUserUploadService::allowedOrgRolesFor(), la
            // misma que usa UsersImport para el flujo por archivo.
            'users.*.organizaciones.*.roles' => 'required|array|min:1',
            'users.*.organizaciones.*.roles.*' => 'required|string|in:' . implode(',', $allowedOrgRoles),
            'users.*.organizaciones.*.hire_date' => 'nullable|date',
            'users.*.organizaciones.*.vacation_balance_initial' => 'nullable|numeric|min:0',
            // RP-B3: departamento/cargo por organización. Sin estas reglas,
            // FormRequest::validated() descarta estas claves del array (solo
            // se conserva lo que tenga una regla declarada), así que el
            // dato editado en el grid nunca llegaría al backend.
            'users.*.organizaciones.*.department' => 'nullable|string|max:255',
            'users.*.organizaciones.*.position' => 'nullable|string|max:255',
            'send_welcome_emails' => 'boolean',
        ];
    }

    /**
     * FIX B2-r1 (feedback temprano): cuando el creador no es root, valida
     * que administre (rol admin/admin_tenant) CADA organización (ruc)
     * referenciada por el grid editado, ANTES de encolar el batch. La
     * garantía dura vive en el worker (ver
     * ProcessUserChunk::tenantOwnershipError), porque este chequeo es
     * "mejor esfuerzo" (UX): si el ruc no resuelve a un tenant aquí, se
     * deja pasar sin error (ya lo reporta otra validación aguas abajo /
     * el propio worker), en vez de duplicar ese mensaje.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $creator = $this->user();
            if (!$creator || $creator->isRoot()) {
                return;
            }

            $users = (array) $this->input('users', []);
            foreach ($users as $i => $user) {
                $organizaciones = $user['organizaciones'] ?? [];
                if (!is_array($organizaciones)) {
                    continue;
                }

                foreach ($organizaciones as $j => $org) {
                    $ruc = is_array($org) ? ($org['ruc'] ?? null) : null;
                    if (empty($ruc)) {
                        continue;
                    }

                    $tenant = Tenant::where('ruc', trim((string) $ruc))->first();
                    if (!$tenant) {
                        continue; // RUC inexistente: ya lo reporta otra vía.
                    }

                    if (
                        !$creator->hasRoleInTenant('admin', $tenant->id)
                        && !$creator->hasRoleInTenant('admin_tenant', $tenant->id)
                    ) {
                        $validator->errors()->add(
                            "users.{$i}.organizaciones.{$j}.ruc",
                            'No tienes permisos para asignar usuarios a esa empresa.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        // Mismo criterio que rules(): la lista mostrada en el mensaje de
        // error debe coincidir con la que de verdad se validó para este actor.
        $allowedOrgRoles = BulkUserUploadService::allowedOrgRolesFor($this->user());

        return [
            'users.required' => 'Debe enviar al menos un usuario.',
            'users.min' => 'Debe enviar al menos un usuario.',
            'users.*.nombre.required' => 'El nombre es requerido para todos los usuarios.',
            'users.*.apellido.required' => 'El apellido es requerido para todos los usuarios.',
            'users.*.email.required' => 'El email es requerido para todos los usuarios.',
            'users.*.email.email' => 'El email debe ser válido.',
            'users.*.tipo_documento.required' => 'El tipo de documento es requerido.',
            'users.*.tipo_documento.in' => 'El tipo de documento debe ser: dni, ce, passport o ruc.',
            'users.*.numero_documento.required' => 'El número de documento es requerido.',
            'users.*.estado.required' => 'El estado es requerido.',
            'users.*.estado.in' => 'El estado debe ser: active o inactive.',
            'users.*.birth_date.date' => 'La fecha de nacimiento no es válida.',
            'users.*.organizaciones.required' => 'Cada usuario debe tener al menos una empresa asignada.',
            'users.*.organizaciones.min' => 'Cada usuario debe tener al menos una empresa asignada.',
            'users.*.organizaciones.*.roles.required' => 'Debes asignar al menos un rol en cada empresa.',
            'users.*.organizaciones.*.roles.min' => 'Debes asignar al menos un rol en cada empresa.',
            'users.*.organizaciones.*.roles.*.in' => 'Uno de los roles por organización es inválido (permitidos: '
                . implode(', ', $allowedOrgRoles) . ').',
            'users.*.organizaciones.*.hire_date.date' => 'La fecha de ingreso de una organización no es válida.',
            'users.*.organizaciones.*.vacation_balance_initial.numeric' => 'El saldo de vacaciones de una organización debe ser numérico.',
            'users.*.organizaciones.*.vacation_balance_initial.min' => 'El saldo de vacaciones de una organización no puede ser negativo.',
        ];
    }
}
