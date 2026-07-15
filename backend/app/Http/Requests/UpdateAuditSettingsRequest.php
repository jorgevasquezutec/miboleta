<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuditSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real (isRoot) se valida en AuditSettingsService,
        // consistente con UpdatePlatformSettingsRequest.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Conjunto de acciones cuya captura se desactiva. El service
            // filtra las always-on y las desconocidas.
            'disabled_actions' => ['present', 'array'],
            'disabled_actions.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'disabled_actions.present' => 'Debe enviarse la lista de acciones desactivadas (puede ir vacía).',
            'disabled_actions.array' => 'Las acciones desactivadas deben enviarse como una lista.',
        ];
    }
}
