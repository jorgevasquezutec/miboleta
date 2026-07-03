<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadUserBatchDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
        return [
            'users' => 'required|array|min:1',
            'users.*.nombre' => 'required|string',
            'users.*.apellido' => 'required|string',
            'users.*.email' => 'required|email',
            'users.*.tipo_documento' => 'required|in:dni,ce,passport,ruc',
            'users.*.numero_documento' => 'required|string',
            'users.*.rol' => 'required|in:client,root,admin',
            'users.*.estado' => 'required|in:active,inactive',
            'users.*.telefono' => 'nullable|string',
            'users.*.organizaciones' => 'nullable|array',
            'users.*.organizaciones.*.ruc' => 'nullable',
            'users.*.organizaciones.*.supervisor_email' => 'nullable',
            // RP1-C: rol(es)/fecha de ingreso/saldo de vacaciones por organización.
            'users.*.organizaciones.*.roles' => 'nullable|array',
            'users.*.organizaciones.*.roles.*' => 'nullable|string|in:admin,client,aprobador,administrador_clientes',
            'users.*.organizaciones.*.hire_date' => 'nullable|date',
            'users.*.organizaciones.*.vacation_balance_initial' => 'nullable|numeric|min:0',
            'send_welcome_emails' => 'boolean',
            'update_existing' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
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
            'users.*.rol.required' => 'El rol es requerido.',
            'users.*.rol.in' => 'El rol debe ser: client, root o admin.',
            'users.*.estado.required' => 'El estado es requerido.',
            'users.*.estado.in' => 'El estado debe ser: active o inactive.',
            'users.*.organizaciones.*.roles.*.in' => 'Uno de los roles por organización es inválido (admin, client, aprobador o administrador_clientes).',
            'users.*.organizaciones.*.hire_date.date' => 'La fecha de ingreso de una organización no es válida.',
            'users.*.organizaciones.*.vacation_balance_initial.numeric' => 'El saldo de vacaciones de una organización debe ser numérico.',
            'users.*.organizaciones.*.vacation_balance_initial.min' => 'El saldo de vacaciones de una organización no puede ser negativo.',
        ];
    }
}
