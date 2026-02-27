@extends('emails.layouts.base')

@section('title', 'Cambio de correo electrónico')
@section('header_title', 'Cambio de correo electrónico')
@section('header_subtitle', 'Su correo electrónico ha sido actualizado')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $user->full_name ?? $user->name }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;">
        Le informamos que el correo electrónico asociado a su cuenta en <strong>{{ config('app.name') }}</strong> ha sido modificado por un administrador.
    </p>

    {{-- Detalles del cambio --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px;">
                <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0;">Correo anterior</p>
                <p style="color: #1E293B; font-size: 16px; font-weight: 600; margin: 0 0 20px 0;">{{ $oldEmail }}</p>
                <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0;">Correo nuevo</p>
                <p style="color: #1E293B; font-size: 16px; font-weight: 600; margin: 0;">{{ $newEmail }}</p>
            </td>
        </tr>
    </table>

    @include('emails.components.alert-danger', [
        'icon' => '🛡️',
        'message' => '<strong>Importante:</strong> Si usted no solicitó este cambio, contacte al administrador inmediatamente.'
    ])

    @include('emails.components.alert-warning', [
        'icon' => '⚠️',
        'message' => '<strong>Nota:</strong> Por seguridad, deberá cambiar su contraseña en el próximo inicio de sesión.'
    ])

    <p style="color: #64748B; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
        Fecha del cambio: <strong>{{ now()->format('d/m/Y H:i') }}</strong>
    </p>

    <p style="color: #64748B; font-size: 14px; line-height: 1.6; margin: 0;">
        Si tiene alguna pregunta, no dude en contactar a su administrador.
    </p>
@endsection
