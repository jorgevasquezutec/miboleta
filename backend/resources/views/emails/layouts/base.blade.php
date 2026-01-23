<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ config('app.name') }}</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #F1F5F9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F1F5F9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background-color: #FFFFFF; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">

                    {{-- Header con fondo claro y texto oscuro (compatible con todos los clientes) --}}
                    <tr>
                        <td bgcolor="#EFF6FF"
                            style="background-color: #EFF6FF; padding: 32px 20px; text-align: center; border-bottom: 3px solid #2563EB;">
                            {{-- Logo MiBoleta --}}
                            <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td align="center">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td bgcolor="#2563EB" style="background-color: #2563EB; width: 50px; height: 50px; border-radius: 10px; text-align: center; vertical-align: middle;">
                                                    <span style="font-size: 24px; color: #FFFFFF;">📄</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 16px;">
                                        <span style="color: #1E40AF; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">{{ config('app.name') }}</span>
                                    </td>
                                </tr>
                            </table>

                            {{-- Título del email --}}
                            <h1 style="color: #1E40AF; font-size: 24px; font-weight: 600; margin: 24px 0 0 0;">
                                @yield('header_title')
                            </h1>
                            @hasSection('header_subtitle')
                                <p style="color: #64748B; margin: 8px 0 0 0; font-size: 15px;">
                                    @yield('header_subtitle')
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- Contenido principal --}}
                    <tr>
                        <td style="padding: 40px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer estandarizado --}}
                    <tr>
                        <td
                            style="background: #F8FAFC; padding: 24px; text-align: center; border-top: 1px solid #E2E8F0;">
                            <p style="color: #64748B; font-size: 14px; font-weight: 600; margin: 0 0 8px 0;">
                                {{ config('app.name') }}
                            </p>
                            <p style="color: #94A3B8; font-size: 12px; margin: 0; line-height: 1.6;">
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