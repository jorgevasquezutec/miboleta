@extends('emails.layouts.base')

@section('title', 'Nuevo Documento Disponible')

@section('header_title', 'Nuevo Documento Disponible')

@section('header_subtitle', 'Tienes un documento pendiente de revisar')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $document->user->name ?? 'Usuario' }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        Tienes un nuevo documento disponible en el portal <strong>{{ config('app.name') }}</strong>.
    </p>

    {{-- Document Details Box --}}
    <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; margin-bottom: 30px; border: 1px solid #E2E8F0;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px; width: 40%;">Tipo de documento:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $document->documentType->display_name ?? 'Documento' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Período:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $document->period }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Fecha de carga:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $document->created_at->format('d/m/Y H:i') }}
                </td>
            </tr>
            @if($document->requires_signature)
                <tr>
                    <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Estado:</td>
                    <td style="padding: 8px 0;">
                        <span
                            style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); color: #92400E; padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-block;">
                            ⚠️ Requiere firma
                        </span>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    @if($document->requires_signature)
        {{-- Alert Box --}}
        <div
            style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
            <p style="color: #92400E; font-size: 14px; margin: 0; line-height: 1.5;">
                <strong>⚠️ Acción requerida:</strong> Este documento requiere tu firma digital.
                Por favor, ingresa al portal para revisar y firmar el documento.
            </p>
        </div>
    @endif

    {{-- CTA Button --}}
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/documents"
            style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
            Ver Documento
        </a>
    </div>

    <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
        Si tienes dudas, contacta al área de Recursos Humanos.
    </p>
@endsection