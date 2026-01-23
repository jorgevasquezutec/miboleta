@extends('emails.layouts.base')

@section('title', '¡Vacaciones Aprobadas!')
@section('header_title', '¡Vacaciones Aprobadas!')
@section('header_subtitle', 'Tu solicitud ha sido aprobada')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $vacationRequest->user->name ?? 'Usuario' }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        ¡Buenas noticias! Tu solicitud de vacaciones ha sido <strong style="color: #059669;">aprobada</strong>.
    </p>

    {{-- Vacation Details Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
        <tr>
            <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; border-radius: 12px; padding: 24px; border: 1px solid #E2E8F0;">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px; width: 40%;">Fechas:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                            {{ $vacationRequest->date_range }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Días:</td>
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
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Aprobado por:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                            {{ $vacationRequest->approvedByUser->full_name ?? 'Supervisor' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Fecha de aprobación:</td>
                        <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                            {{ $vacationRequest->approved_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Estado:</td>
                        <td style="padding: 8px 0;">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td bgcolor="#ECFDF5" style="background-color: #ECFDF5; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); color: #065F46; padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600;">
                                        ✅ Aprobada
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @include('emails.components.alert-success', [
        'icon' => '🎉',
        'message' => '<strong>¡Disfruta tus vacaciones!</strong> Recuerda coordinar con tu equipo antes de ausentarte.'
    ])
    @include('emails.components.button', [
        'url' => config('app.frontend_url', 'http://localhost:5173') . '/vacations',
        'text' => 'Ver Mis Vacaciones'
    ])

        <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
            Si tienes dudas, contacta al área de Recursos Humanos.
        </p>
@endsection
