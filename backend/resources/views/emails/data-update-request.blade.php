@extends('emails.layouts.base')

@section('title', 'Solicitud de Actualización de Datos')
@section('header_title', 'Solicitud de Actualización de Datos')
@section('header_subtitle', 'Un colaborador solicita actualizar su información personal')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        <strong>{{ $requester->full_name }}</strong> solicita actualizar algunos de sus datos personales en
        <strong>{{ $tenant->name }}</strong>.
    </p>

    {{-- Requester Details Box --}}
    <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; margin-bottom: 30px; border: 1px solid #E2E8F0;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px; width: 40%;">Colaborador:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $requester->full_name }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Documento:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                    {{ $requester->document_type }} {{ $requester->document_text }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Email:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                    {{ $requester->email }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Empresa:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $tenant->name }}
                </td>
            </tr>
            @if($requestedChanges)
                <tr>
                    <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Campos a actualizar:</td>
                    <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                        @if(is_array($requestedChanges))
                            {{ implode(', ', $requestedChanges) }}
                        @else
                            {{ $requestedChanges }}
                        @endif
                    </td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Message Box --}}
    <div style="margin-bottom: 30px;">
        <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0; font-weight: 600;">Mensaje del colaborador:</p>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px;">
            <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0; white-space: pre-line;">{{ $message }}</p>
        </div>
    </div>

    @include('emails.components.alert-warning', [
        'icon' => '✏️',
        'message' => '<strong>Acción requerida:</strong> Contacta al colaborador o actualiza sus datos desde el portal administrativo.'
    ])
    @include('emails.components.button', [
        'url' => config('app.frontend_url', 'http://localhost:5173') . '/users',
        'text' => 'Ir al Portal Administrativo'
    ])

        <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
            Si tienes dudas, contacta directamente al colaborador.
        </p>
@endsection
