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

    @include('emails.components.button', ['url' => $resetUrl, 'text' => 'Restablecer Contraseña'])

    @include('emails.components.alert-warning', [
        'icon' => '⏱️',
        'message' => '<strong>Este enlace expirará en ' . $expiresInMinutes . ' minutos.</strong>'
    ])
    @include('emails.components.info-box', [
        'content' => '<p style="color: #64748B; font-size: 14px; margin: 0;">🛡️ Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</p><p style="color: #94A3B8; font-size: 13px; margin: 8px 0 0 0;">Tu contraseña actual permanecerá sin cambios.</p>'
    ])
    <p style="color: #94A3B8; font-size: 13px; margin: 0 0 10px 0;">
        Si el botón no funciona, copia y pega este enlace en tu navegador:
    </p>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td bgcolor="#F1F5F9" style="background-color: #F1F5F9; padding: 12px; border-radius: 8px;">
                <p style="word-break: break-all; font-size: 12px; color: #64748B; margin: 0;">
                    {{ $resetUrl }}
                </p>
            </td>
        </tr>
    </table>
@endsection