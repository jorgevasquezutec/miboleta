<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVacationRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            // Tope de sanidad (1 año calendario). El límite real de negocio
            // lo aplica VacationService::validateSufficientBalance contra el
            // Saldo Vacaciones del usuario (VacationBalanceService::getBalance
            // ()['balance']), que puede superar los 30 días si acumuló varios
            // años de servicio.
            'days_requested' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o una fecha futura.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'days_requested.required' => 'La cantidad de días es obligatoria.',
            'days_requested.min' => 'Debes solicitar al menos medio día.',
            'days_requested.max' => 'No puedes solicitar más de 365 días.',
            'reason.max' => 'El motivo no puede exceder 1000 caracteres.',
        ];
    }
}
