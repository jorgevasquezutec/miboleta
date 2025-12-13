@extends('emails.layouts.base')

@section('title', 'Bienvenido')
@section('header_title', '¡Bienvenido!')
@section('header_subtitle', 'Tu cuenta ha sido creada exitosamente')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $user->full_name ?? $user->name }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;">
        Tu cuenta en <strong>{{ config('app.name') }}</strong> ha sido creada exitosamente. A continuación
        encontrarás tus credenciales de acceso:
    </p>

    {{-- Credenciales --}}
    <div
        style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
        <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0;">Correo electrónico</p>
        <p style="color: #1E293B; font-size: 18px; font-weight: 600; margin: 0 0 20px 0;">{{ $user->email }}</p>
        <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">Contraseña temporal</p>
        @include('emails.components.code-box', ['code' => $temporaryPassword])
    </div>

    @include('emails.components.alert-warning', [
        'icon' => '⚠️',
        'message' => '<strong>Importante:</strong> Por seguridad, deberás cambiar tu contraseña cuando inicies sesión por primera vez.'
    ])
    @include('emails.components.button', ['url' => $loginUrl, 'text' => 'Iniciar Sesión'])
        <p style="color: #64748B; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
        Si tienes alguna pregunta, no dudes en contactar a tu administrador.
    </p>
        <p style="color: #334155; font-size: 15px; font-weight: 500; margin: 20px 0 0 0;">
            ¡Te damos la bienvenida al equipo! 🚀
        </p>
@endsection