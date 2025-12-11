@component('mail::message')
{{-- Header --}}
<div style="text-align: center; margin-bottom: 32px;">
    <div
        style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 20px; border-radius: 16px; margin-bottom: 16px;">
        <span style="font-size: 36px;">🔑</span>
    </div>
    <h1 style="color: #1E40AF; font-size: 28px; font-weight: 600; margin: 0;">Recuperar Contraseña</h1>
</div>

Hola **{{ $user->full_name ?? $user->name }}**,

Recibimos una solicitud para restablecer la contraseña de tu cuenta en {{ config('app.name') }}.

Haz clic en el siguiente botón para crear una nueva contraseña:

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Restablecer Contraseña
@endcomponent

{{-- Información de expiración --}}
@component('mail::subcopy')
<div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px 16px; border-radius: 0 8px 8px 0;">
    <strong style="color: #92400E;">⏱️ Este enlace expirará en {{ $expiresInMinutes }} minutos.</strong>
</div>
@endcomponent

{{-- Nota de seguridad --}}
@component('mail::panel')
<div style="text-align: center; color: #64748B; font-size: 14px;">
    <p style="margin: 0;">🛡️ Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</p>
    <p style="margin: 8px 0 0 0;">Tu contraseña actual permanecerá sin cambios.</p>
</div>
@endcomponent

Si el botón no funciona, copia y pega este enlace en tu navegador:

<p
    style="word-break: break-all; font-size: 12px; color: #64748B; background: #F1F5F9; padding: 12px; border-radius: 8px;">
    {{ $resetUrl }}
</p>

Saludos,<br>
**{{ config('app.name') }}**

{{-- Footer --}}
@slot('subcopy')
<p style="color: #94A3B8; font-size: 12px; text-align: center;">
    Este correo fue enviado automáticamente. Por favor, no respondas a este mensaje.<br>
    © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
</p>
@endslot
@endcomponent