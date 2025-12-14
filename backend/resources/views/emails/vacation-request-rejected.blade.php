@extends('emails.layouts.base')

@section('title', 'Solicitud de Vacaciones Rechazada')
@section('header_title', 'Solicitud Rechazada')
@section('header_subtitle', 'Tu solicitud no ha sido aprobada')

@section('content')
    <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
        Hola <strong>{{ $vacationRequest->user->name ?? 'Usuario' }}</strong>,
    </p>

    <p style="color: #64748B; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
        Lamentamos informarte que tu solicitud de vacaciones ha sido <strong style="color: #DC2626;">rechazada</strong>.
    </p>

    {{-- Vacation Details Box --}}
    <div style="background: #F8FAFC; border-radius: 12px; padding: 24px; margin-bottom: 30px; border: 1px solid #E2E8F0;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px; width: 40%;">Fechas solicitadas:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $vacationRequest->date_range }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Días:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                    {{ $vacationRequest->duration_text }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Rechazado por:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px; font-weight: 600;">
                    {{ $vacationRequest->rejectedByUser->full_name ?? 'Supervisor' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Fecha:</td>
                <td style="padding: 8px 0; color: #334155; font-size: 14px;">
                    {{ $vacationRequest->rejected_at->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748B; font-size: 14px;">Estado:</td>
                <td style="padding: 8px 0;">
                    <span
                        style="background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%); color: #991B1B; padding: 6px 14px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-block;">
                        ❌ Rechazada
                    </span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Rejection Reason Box --}}
    @if($vacationRequest->rejection_reason)
        <div
            style="background: #FEF2F2; border-left: 4px solid #DC2626; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
            <p style="color: #991B1B; font-size: 14px; margin: 0 0 8px 0; font-weight: 600;">
                📝 Motivo del rechazo:
            </p>
            <p style="color: #7F1D1D; font-size: 14px; margin: 0; line-height: 1.6;">
                {{ $vacationRequest->rejection_reason }}
            </p>
        </div>
    @endif

    @include('emails.components.alert-warning', [
        'icon' => '💡',
        'message' => 'Puedes crear una nueva solicitud con fechas diferentes o contactar a tu supervisor para más información.'
    ])
    @include('emails.components.button', [
        'url' => config('app.frontend_url', 'http://localhost:5173') . '/vacations/new',
        'text' => 'Nueva Solicitud'
    ])

        <p style="color: #94A3B8; font-size: 14px; margin: 0; text-align: center;">
            Si tienes dudas, contacta al área de Recursos Humanos.
        </p>
@endsection
