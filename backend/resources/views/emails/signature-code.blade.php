@extends('emails.layouts.base')

@section('title', 'Código de Verificación')

@section('header_title', 'Código de Verificación')

@section('header_subtitle', 'Para firmar tu documento')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $userName }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        Has solicitado firmar el siguiente documento:
    </p>

    {{-- Document Info --}}
    <div
        style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border-radius: 8px; padding: 15px 20px; margin-bottom: 30px;">
        <p style="color: #1E40AF; font-size: 14px; margin: 0; font-weight: 600;">
            📄 {{ $documentType }} - Período {{ $period }}
        </p>
    </div>

    {{-- Code Box --}}
    <div style="text-align: center; margin-bottom: 30px;">
        <div
            style="background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 30px 50px; border-radius: 12px; display: inline-block;">
            <p
                style="color: rgba(255,255,255,0.8); font-size: 12px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px;">
                Tu código de verificación
            </p>
            <p
                style="color: #ffffff; font-size: 42px; font-weight: 700; margin: 0; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                {{ $code }}
            </p>
        </div>
    </div>

    {{-- Warning Box --}}
    <div
        style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 24px;">
        <p style="color: #92400E; font-size: 14px; margin: 0 0 8px 0; line-height: 1.5;">
            <strong>⚠️ Importante:</strong>
        </p>
        <ul style="color: #92400E; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.8;">
            <li>Este código expira en <strong>5 minutos</strong></li>
            <li>Tienes máximo <strong>3 intentos</strong></li>
            <li><strong>No compartas</strong> este código con nadie</li>
        </ul>
    </div>

    {{-- Security Notice --}}
    <div style="background: #FEF2F2; border-left: 4px solid #EF4444; border-radius: 0 8px 8px 0; padding: 16px;">
        <p style="color: #991B1B; font-size: 14px; margin: 0; line-height: 1.5;">
            <strong>🛡️ Seguridad:</strong> Si no solicitaste este código, ignora este mensaje y contacta inmediatamente a
            Recursos Humanos.
        </p>
    </div>

    <p style="color: #94A3B8; font-size: 13px; margin: 30px 0 0 0; text-align: center;">
        Tu firma digital tiene validez legal según la Ley de Firmas Digitales.
    </p>
@endsection