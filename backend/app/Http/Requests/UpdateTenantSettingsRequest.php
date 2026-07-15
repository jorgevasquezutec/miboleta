<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    use ResolvesActiveRole;

    public function authorize(): bool
    {
        // Rol resuelto en la empresa ACTIVA (ver trait), no con el respaldo
        // global de roles. Mismo conjunto de roles que antes.
        return $this->allowsAbility('tenants.update_settings');
    }

    public function rules(): array
    {
        return [
            'supervisor_id' => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_id.exists' => 'El supervisor seleccionado no existe',
        ];
    }
}
