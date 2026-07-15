<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesActiveRole;
use Illuminate\Foundation\Http\FormRequest;

class AssignOrphanRequest extends FormRequest
{
    use ResolvesActiveRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo root y admin pueden asignar documentos huérfanos
        // Rol resuelto en la empresa ACTIVA (ver trait), no con el respaldo
        // global de roles. Mismo conjunto de roles que antes.
        return $this->allowsAbility('documents.assign_orphan');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
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
            'user_id.required' => 'El usuario es requerido',
            'user_id.exists' => 'El usuario seleccionado no existe',
        ];
    }
}
