<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && \in_array($this->user()->getCurrentRole(), ['root', 'admin'], true);
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
