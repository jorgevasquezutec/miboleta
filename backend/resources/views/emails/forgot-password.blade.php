@extends('emails.layouts.base')

@section('title', 'Recuperar Contraseña')

@section('header_title', 'Recuperar Contraseña')

@section('header_subtitle', 'Solicitud de restablecimiento')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $user->full_name ?? $user->name }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta en
        <strong>{{ config('app.name') }}</strong>.
    </p>

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;">
        Haz clic en el siguiente botón para crear una nueva contraseña:
    </p>

    {{-- Botón principal --}}
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}"
            style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Restablecer Contraseña
        </a>
    </div>

    {{-- Alerta de expiración --}}
    <div
        style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
        <p style="color: #92400E; font-size: 14px; margin: 0;">
            <strong>⏱️ Este enlace expirará en {{ $expiresInMinutes }} minutos.</strong>
        </p>
    </div>

    {{-- Nota de seguridad --}}
    <div style="background: #F8FAFC; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 30px;">
        <p style="color: #64748B; font-size: 14px; margin: 0;">
            🛡️ Si no solicitaste este cambio, puedes ignorar este correo de forma segura.
        </p>
        <p style="color: #94A3B8; font-size: 13px; margin: 8px 0 0 0;">
            Tu contraseña actual permanecerá sin cambios.
        </p>
    </div>

    {{-- Link alternativo --}}
    <p style="color: #94A3B8; font-size: 13px; margin: 0 0 10px 0;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:
    </p>
    <p
        style="word-break: break-all; font-size: 12px; color: #64748B; background: #F1F5F9; padding: 12px; border-radius: 8px; margin: 0;">
        {{ $resetUrl }}
    </p>
@endsection