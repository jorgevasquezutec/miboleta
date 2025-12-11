<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends CustomFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenantId = $this->route('tenant');
        $user = $this->user();

        // Root puede actualizar cualquier tenant
        if ($user && $user->isRoot()) {
            return true;
        }

        // Admin solo puede actualizar sus tenants
        $tenant = Tenant::find($tenantId);
        return $user && $tenant && $tenant->hasUser($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get tenant ID from route parameter (it's a string, not a model)
        $tenantId = $this->route('tenant');

        return [
            'name' => 'sometimes|required|string|max:255',
            'ruc' => [
                'sometimes',
                'required',
                'string',
                'size:11',
                Rule::unique('tenants')->ignore($tenantId)
            ],
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'logo_path' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive,suspended',
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
            'name.required' => 'El nombre del tenant es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'ruc.required' => 'El RUC es requerido',
            'ruc.size' => 'El RUC debe tener exactamente 11 dígitos',
            'ruc.unique' => 'Este RUC ya está registrado',
            'business_name.max' => 'La razón social no puede exceder 255 caracteres',
            'address.max' => 'La dirección no puede exceder 500 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'status.in' => 'El estado debe ser: active, inactive o suspended',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'No autorizado para actualizar este tenant'
        );
    }
}
