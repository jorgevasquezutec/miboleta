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
        ];
    }
}
