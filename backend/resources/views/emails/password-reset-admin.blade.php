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
        {{-- Caja de contraseña --}}
        <div
            style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 30px;">
            <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">
                Tu nueva contraseña es:
            </p>
            <p
                style="font-family: 'Courier New', monospace; background: #FFFFFF; color: #2563EB; padding: 16px 24px; border-radius: 8px; font-size: 22px; font-weight: 700; letter-spacing: 3px; display: inline-block; border: 1px solid #E2E8F0; margin: 0;">
                {{ $newPassword }}
            </p>
        </div>
    @endif

    @if($mustChangePassword)
        {{-- Alerta de cambio obligatorio --}}
        <div
            style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
            <p style="color: #92400E; font-size: 14px; margin: 0;">
                <strong>⚠️ Cambio requerido:</strong> Deberás establecer una nueva contraseña cuando inicies sesión.
            </p>
        </div>
    @else
        {{-- Recomendación --}}
        <div
            style="background: #ECFDF5; border-left: 4px solid #10B981; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
            <p style="color: #065F46; font-size: 14px; margin: 0;">
                <strong>💡 Recomendación:</strong> Te sugerimos cambiar tu contraseña después de iniciar sesión para mayor
                seguridad.
            </p>
        </div>
    @endif

    {{-- Botón --}}
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $loginUrl }}"
            style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Iniciar Sesión
        </a>
    </div>

    <p
        style="color: #94A3B8; font-size: 14px; line-height: 1.6; margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #E2E8F0;">
        Si no reconoces esta actividad, contacta inmediatamente a tu administrador.
    </p>
@endsection