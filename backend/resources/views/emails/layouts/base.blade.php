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

                    {{-- Header con logo y gradiente azul --}}
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); padding: 32px 20px; text-align: center;">
                            {{-- Logo MiBoleta --}}
                            <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td align="center">
                                        <div
                                            style="background: rgba(255,255,255,0.15); width: 64px; height: 64px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z"
                                                    stroke="white" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M14 2V8H20" stroke="white" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M16 13H8" stroke="white" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M16 17H8" stroke="white" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M10 9H9H8" stroke="white" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 16px;">
                                        <span
                                            style="color: #FFFFFF; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">{{ config('app.name') }}</span>
                                    </td>
                                </tr>
                            </table>

                            {{-- Título del email --}}
                            <h1 style="color: #FFFFFF; font-size: 24px; font-weight: 600; margin: 24px 0 0 0;">
                                @yield('header_title')
                            </h1>
                            @hasSection('header_subtitle')
                                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0 0; font-size: 15px;">
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