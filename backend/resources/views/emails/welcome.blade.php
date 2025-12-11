<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido - {{ config('app.name') }}</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F1F5F9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F1F5F9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #FFFFFF; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">

                    {{-- Header con gradiente --}}
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 40px 20px; text-align: center;">
                            <div
                                style="background: rgba(255,255,255,0.2); width: 80px; height: 80px; border-radius: 50%; display: inline-block; line-height: 80px;">
                                <span style="font-size: 40px;">🎉</span>
                            </div>
                            <h1 style="color: #FFFFFF; font-size: 24px; font-weight: 600; margin: 20px 0 0 0;">
                                ¡Bienvenido a {{ config('app.name') }}!
                            </h1>
                        </td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #334155; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hola <strong>{{ $user->full_name ?? $user->name }}</strong>,
                            </p>

                            <p style="color: #64748B; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;">
                                Tu cuenta ha sido creada exitosamente. A continuación encontrarás tus credenciales de
                                acceso:
                            </p>

                            {{-- Caja de credenciales --}}
                            <div
                                style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
                                <p style="color: #64748B; font-size: 14px; margin: 0 0 8px 0;">
                                    Correo electrónico
                                </p>
                                <p style="color: #1E293B; font-size: 18px; font-weight: 600; margin: 0 0 20px 0;">
                                    {{ $user->email }}
                                </p>

                                <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">
                                    Contraseña temporal
                                </p>
                                <p
                                    style="font-family: 'Courier New', monospace; background: #FFFFFF; color: #1E40AF; padding: 16px 24px; border-radius: 8px; font-size: 22px; font-weight: 700; letter-spacing: 3px; display: inline-block; border: 1px solid #E2E8F0; margin: 0;">
                                    {{ $temporaryPassword }}
                                </p>
                            </div>

                            {{-- Alerta de seguridad --}}
                            <div
                                style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
                                <p style="color: #92400E; font-size: 14px; margin: 0;">
                                    <strong>⚠️ Importante:</strong> Por seguridad, deberás cambiar tu contraseña cuando
                                    inicies sesión por primera vez.
                                </p>
                            </div>

                            {{-- Botón --}}
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="{{ $loginUrl }}"
                                    style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
                                    Iniciar Sesión
                                </a>
                            </div>

                            <p style="color: #64748B; font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
                                Si tienes alguna pregunta, no dudes en contactar a tu administrador.
                            </p>

                            <p style="color: #334155; font-size: 15px; font-weight: 500; margin: 20px 0 0 0;">
                                ¡Te damos la bienvenida al equipo! 🚀
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td
                            style="background: #F8FAFC; padding: 24px; text-align: center; border-top: 1px solid #E2E8F0;">
                            <p style="color: #64748B; font-size: 14px; font-weight: 600; margin: 0 0 8px 0;">
                                {{ config('app.name') }}
                            </p>
                            <p style="color: #94A3B8; font-size: 12px; margin: 0;">
                                Este correo fue enviado automáticamente. Por favor, no respondas a este mensaje.<br>
                                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>