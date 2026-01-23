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
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E2E8F0;">
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
                                <table cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td bgcolor="#FEF3C7" style="background-color: #FEF3C7; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); color: #92400E; padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600;">
                                            ⚠️ Requiere firma
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if($document->requires_signature)
        @include('emails.components.alert-warning', [
            'icon' => '⚠️',
            'message' => '<strong>Acción requerida:</strong> Este documento requiere tu firma digital. Por favor, ingresa al portal para revisar y firmar el documento.'
        ])
    @endif

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
        Haz clic en el siguiente botón para ver tu documento:
    </p>

    @include('emails.components.button', [
        'url' => config('app.frontend_url', 'http://localhost:5173') . '/documents',
        'text' => 'Ver Documento'
    ])

        <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
            Si tienes dudas, contacta al área de Recursos Humanos.
        </p>
@endsection