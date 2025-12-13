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

    {{-- Caja de credenciales --}}
    <div
        style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
        <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0;">
            Correo electrónico
        </p>
        <p style="color: #1E293B; font-size: 18px; font-weight: 600; margin: 0 0 20px 0;">
            {{ $user->email }}
        </p>

        <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">
            Contraseña temporal
        </p>
        <p
            style="font-family: 'Courier New', monospace; background: #FFFFFF; color: #2563EB; padding: 16px 24px; border-radius: 8px; font-size: 22px; font-weight: 700; letter-spacing: 3px; display: inline-block; border: 1px solid #E2E8F0; margin: 0;">
            {{ $temporaryPassword }}
        </p>
    </div>

    {{-- Alerta de seguridad --}}
    <div
        style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
        <p style="color: #92400E; font-size: 14px; margin: 0;">
            <strong>⚠️ Importante:</strong> Por seguridad, deberás cambiar tu contraseña cuando
            inicies sesión por primera vez.
        </p>
    </div>

    {{-- Botón principal --}}
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $loginUrl }}"
            style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Iniciar Sesión
        </a>
    </div>

    <p style="color: #64748B; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
        Si tienes alguna pregunta, no dudes en contactar a tu administrador.
    </p>

    <p style="color: #334155; font-size: 15px; font-weight: 500; margin: 20px 0 0 0;">
        ¡Te damos la bienvenida al equipo! 🚀
    </p>
@endsection