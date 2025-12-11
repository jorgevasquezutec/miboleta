@component('mail::message')
{{-- Header --}}
<div style="text-align: center; margin-bottom: 32px;">
    <div
        style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 20px; border-radius: 16px; margin-bottom: 16px;">
        <span style="font-size: 36px;">🔐</span>
    </div>
    <h1 style="color: #1E40AF; font-size: 28px; font-weight: 600; margin: 0;">Tu contraseña ha sido restablecida</h1>
</div>

Hola **{{ $user->full_name ?? $user->name }}**,

Un administrador ha restablecido tu contraseña en {{ config('app.name') }}.

@if($newPassword)
    {{-- Nueva contraseña generada --}}
    @component('mail::panel')
    <div style="text-align: center;">
        <p style="color: #64748B; font-size: 14px; margin-bottom: 8px;">Tu nueva contraseña es:</p>
        <p
            style="font-family: monospace; background: #F1F5F9; color: #1E40AF; padding: 12px 20px; border-radius: 8px; font-size: 20px; font-weight: 700; letter-spacing: 2px; display: inline-block;">
            {{ $newPassword }}</p>
    </div>
    @endcomponent
@endif

@if($mustChangePassword)
    {{-- Alerta de cambio obligatorio --}}
    @component('mail::subcopy')
    <div style="background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 12px 16px; border-radius: 0 8px 8px 0;">
        <strong style="color: #92400E;">⚠️ Cambio requerido:</strong>
        <span style="color: #92400E;"> Deberás establecer una nueva contraseña cuando inicies sesión.</span>
    </div>
    @endcomponent
@else
    {{-- Recomendación de seguridad --}}
    @component('mail::subcopy')
    <div style="background: #DBEAFE; border-left: 4px solid #2563EB; padding: 12px 16px; border-radius: 0 8px 8px 0;">
        <strong style="color: #1E40AF;">💡 Recomendación:</strong>
        <span style="color: #1E40AF;"> Te sugerimos cambiar tu contraseña después de iniciar sesión para mayor
            seguridad.</span>
    </div>
    @endcomponent
@endif

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Iniciar Sesión
@endcomponent

Si no reconoces esta actividad, contacta inmediatamente a tu administrador.

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