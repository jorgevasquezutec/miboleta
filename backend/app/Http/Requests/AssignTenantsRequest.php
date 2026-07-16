<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class AssignTenantsRequest extends FormRequest
{
    use ResolvesActiveRole;

    public function authorize(): bool
    {
        // Solo root y admin pueden asignar usuarios a tenants
        // Rol resuelto en la empresa ACTIVA (ver trait), no con el respaldo
        // global de roles. Mismo conjunto de roles que antes.
        return $this->allowsAbility('tenants.assign_users');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'is_primary' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es requerido',
            'user_id.exists' => 'El usuario seleccionado no existe',
            'is_primary.boolean' => 'El campo debe ser verdadero o falso',
        ];
    }
}
