@extends('emails.layouts.base')

@section('title', 'Contraseña Restablecida')
@section('header_title', 'Contraseña Restablecida')
@section('header_subtitle', 'Tu contraseña ha sido actualizada')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $user->full_name ?? $user->name }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;">
        Un administrador ha restablecido tu contraseña en <strong>{{ config('app.name') }}</strong>.
    </p>

    @if($newPassword)
        @include('emails.components.code-box', ['label' => 'Tu nueva contraseña es:', 'code' => $newPassword])
    @endif

    @if($mustChangePassword)
        @include('emails.components.alert-warning', [
            'icon' => '⚠️',
            'message' => '<strong>Cambio requerido:</strong> Deberás establecer una nueva contraseña cuando inicies sesión.'
        ])
    @else
        @include('emails.components.alert-success', [
            'icon' => '💡',
            'message' => '<strong>Recomendación:</strong> Te sugerimos cambiar tu contraseña después de iniciar sesión para mayor seguridad.'
        ])
    @endif

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
        Haz clic en el siguiente botón para acceder a tu cuenta:
    </p>

    @include('emails.components.button', ['url' => $loginUrl, 'text' => 'Iniciar Sesión'])

        <p style="color: #94A3B8; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #E2E8F0;">
            Si no reconoces esta actividad, contacta inmediatamente a tu administrador.
        </p>
@endsection