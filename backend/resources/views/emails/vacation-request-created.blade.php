@extends('emails.layouts.base')

@section('title', 'Nueva Solicitud de Vacaciones')
@section('header_title', 'Nueva Solicitud de Vacaciones')
@section('header_subtitle', 'Un empleado de tu equipo solicita vacaciones')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        <strong>{{ $employee->full_name }}</strong> ha solicitado vacaciones y está esperando tu aprobación.
    </p>

    {{-- Vacation Details Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E2E8F0;">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px; width: 40%;">Empleado:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                            {{ $employee->full_name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Email:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                            {{ $employee->email }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Fechas:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                            {{ $vacationRequest->date_range }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Días solicitados:</td>
                        <td style="padding: 8px 0;">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td bgcolor="#DBEAFE" style="background-color: #DBEAFE; background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%); color: #1E40AF; padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600;">
                                        🏖️ {{ $vacationRequest->duration_text }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if($vacationRequest->reason)
                        <tr>
                            <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Motivo:</td>
                            <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                                {{ $vacationRequest->reason }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Fecha de solicitud:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                            {{ $vacationRequest->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @include('emails.components.alert-warning', [
        'icon' => '⏳',
        'message' => '<strong>Acción requerida:</strong> Por favor, ingresa al portal para aprobar o rechazar esta solicitud.'
    ])

    <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
        Haz clic en el siguiente botón para revisar la solicitud:
    </p>

    @include('emails.components.button', [
        'url' => config('app.frontend_url', 'http://localhost:5173') . '/team-vacations',
        'text' => 'Revisar Solicitud'
    ])

            <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
                Si tienes dudas, contacta al área de Recursos Humanos.
            </p>
@endsection
