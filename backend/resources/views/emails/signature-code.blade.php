<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación</title>
</head>

<body
    style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
        style="background-color: #f5f5f5; padding: 20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0"
                    style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header -->
                    <tr>
                        <td
                            style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">
                                🔐 Código de Verificación
                            </h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;">
                                Para firmar tu documento
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hola <strong>{{ $userName }}</strong>,
                            </p>

                            <p style="color: #666666; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                Has solicitado firmar el siguiente documento:
                            </p>

                            <!-- Document Info -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="background-color: #f0fdf4; border-radius: 8px; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 15px 20px;">
                                        <p style="color: #166534; font-size: 14px; margin: 0;">
                                            <strong>{{ $documentType }}</strong> - Período {{ $period }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Code Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="margin-bottom: 30px;">
                                <tr>
                                    <td align="center">
                                        <div
                                            style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%); padding: 30px 50px; border-radius: 12px; display: inline-block;">
                                            <p
                                                style="color: #9ca3af; font-size: 12px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px;">
                                                Tu código de verificación
                                            </p>
                                            <p
                                                style="color: #ffffff; font-size: 42px; font-weight: 700; margin: 0; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                                {{ $code }}
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Warning Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                            <strong>⚠️ Importante:</strong>
                                        </p>
                                        <ul
                                            style="color: #92400e; font-size: 14px; margin: 10px 0 0 0; padding-left: 20px; line-height: 1.8;">
                                            <li>Este código expira en <strong>5 minutos</strong></li>
                                            <li>Tienes máximo <strong>3 intentos</strong></li>
                                            <li><strong>No compartas</strong> este código con nadie</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Notice -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 15px;">
                                        <p style="color: #991b1b; font-size: 14px; margin: 0; line-height: 1.5;">
                                            <strong>🛡️ Seguridad:</strong> Si no solicitaste este código, ignora este
                                            mensaje y contacta inmediatamente a Recursos Humanos.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="color: #6c757d; font-size: 12px; margin: 0 0 10px 0;">
                                Este correo fue enviado automáticamente desde el portal MiBoleta.
                            </p>
                            <p style="color: #6c757d; font-size: 12px; margin: 0;">
                                Tu firma digital tiene validez legal según la Ley de Firmas Digitales.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>