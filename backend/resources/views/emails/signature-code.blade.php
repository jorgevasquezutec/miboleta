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
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td bgcolor="#DBEAFE" style="background-color: #DBEAFE; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border-radius: 8px; padding: 15px 20px;">
                <p style="color: #1E40AF; font-size: 14px; margin: 0; font-weight: 600;">
                    📄 {{ $documentType }} - Período {{ $period }}
                </p>
            </td>
        </tr>
    </table>

    {{-- Code Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" bgcolor="#EFF6FF"
                    style="background-color: #EFF6FF; border: 2px solid #2563EB; border-radius: 12px;">
                    <tr>
                        <td style="padding: 30px 50px; text-align: center;">
                            <p style="color: #64748B; font-size: 12px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px;">
                                Tu código de verificación
                            </p>
                            <p style="color: #1E40AF; font-size: 42px; font-weight: 700; margin: 0; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                {{ $code }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Warning Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
        <tr>
            <td bgcolor="#FEF3C7" style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px;">
                <p style="color: #92400E; font-size: 14px; margin: 0 0 8px 0; line-height: 1.5;">
                    <strong>⚠️ Importante:</strong>
                </p>
                <p style="color: #92400E; font-size: 14px; margin: 0; line-height: 1.8;">
                    • Este código expira en <strong>5 minutos</strong><br>
                    • Tienes máximo <strong>3 intentos</strong><br>
                    • <strong>No compartas</strong> este código con nadie
                </p>
            </td>
        </tr>
    </table>

    @include('emails.components.alert-danger', [
        'icon' => '🛡️',
        'message' => '<strong>Seguridad:</strong> Si no solicitaste este código, ignora este mensaje y contacta inmediatamente a Recursos Humanos.'
    ])
        <p style="color: #94A3B8; font-size: 13px; margin: 20px 0 0 0; text-align: center;">
            Tu firma digital tiene validez legal según la Ley de Firmas Digitales.
        </p>
@endsection