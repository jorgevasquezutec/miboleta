@component('mail::message')
{{-- Header con gradiente azul --}}
<div style="text-align: center; margin-bottom: 32px;">
    <div
        style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 20px; border-radius: 16px; margin-bottom: 16px;">
        <span style="font-size: 36px;">🎉</span>
    </div>
    <h1 style="color: #1E40AF; font-size: 28px; font-weight: 600; margin: 0;">¡Bienvenido a {{ config('app.name') }}!
    </h1>
</div>

Hola **{{ $user->full_name ?? $user->name }}**,

Tu cuenta ha sido creada exitosamente. A continuación encontrarás tus credenciales de acceso:

{{-- Card con credenciales --}}
@component('mail::panel')
<div style="text-align: center;">
    <p style="color: #64748B; font-size: 14px; margin-bottom: 8px;">Correo electrónico</p>
    <p style="color: #1E293B; font-size: 18px; font-weight: 600; margin-bottom: 20px;">{{ $user->email }}</p>

    <p style="color: #64748B; font-size: 14px; margin-bottom: 8px;">Contraseña temporal</p>
    <p
        style="font-family: monospace; background: #F1F5F9; color: #1E40AF; padding: 12px 20px; border-radius: 8px; font-size: 20px; font-weight: 700; letter-spacing: 2px; display: inline-block;">
        {{ $temporaryPassword }}</p>
</div>
@endcomponent

{{-- Alerta de seguridad --}}
@component('mail::subcopy')
<div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px 16px; border-radius: 0 8px 8px 0;">
    <strong style="color: #92400E;">⚠️ Importante:</strong>
    <span style="color: #92400E;"> Por seguridad, deberás cambiar tu contraseña cuando inicies sesión por primera
        vez.</span>
</div>
@endcomponent

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Iniciar Sesión
@endcomponent

Si tienes alguna pregunta, no dudes en contactar a tu administrador.

¡Te damos la bienvenida al equipo!

**{{ config('app.name') }}**

{{-- Footer --}}
@slot('subcopy')
<p style="color: #94A3B8; font-size: 12px; text-align: center;">
    Este correo fue enviado automáticamente. Por favor, no respondas a este mensaje.<br>
    © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
</p>
@endslot
@endcomponent