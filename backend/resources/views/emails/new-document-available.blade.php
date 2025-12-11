<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Documento Disponible</title>
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
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">
                                📄 Nuevo Documento Disponible
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Hola <strong>{{ $document->user->name ?? 'Usuario' }}</strong>,
                            </p>

                            <p style="color: #666666; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                Tienes un nuevo documento disponible en el portal MiBoleta.
                            </p>

                            <!-- Document Details Box -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td
                                                    style="padding: 8px 0; color: #666666; font-size: 14px; width: 40%;">
                                                    Tipo de documento:</td>
                                                <td
                                                    style="padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600;">
                                                    {{ $document->documentType->display_name ?? 'Documento' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666666; font-size: 14px;">Período:
                                                </td>
                                                <td
                                                    style="padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600;">
                                                    {{ $document->period }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666666; font-size: 14px;">Fecha de
                                                    carga:</td>
                                                <td
                                                    style="padding: 8px 0; color: #333333; font-size: 14px; font-weight: 600;">
                                                    {{ $document->created_at->format('d/m/Y H:i') }}</td>
                                            </tr>
                                            @if($document->requires_signature)
                                                <tr>
                                                    <td style="padding: 8px 0; color: #666666; font-size: 14px;">Estado:
                                                    </td>
                                                    <td style="padding: 8px 0;">
                                                        <span
                                                            style="background-color: #ffc107; color: #333333; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                                            ⚠️ Requiere firma
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            @if($document->requires_signature)
                                <!-- Alert Box -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                    style="background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 30px;">
                                    <tr>
                                        <td style="padding: 15px;">
                                            <p style="color: #856404; font-size: 14px; margin: 0; line-height: 1.5;">
                                                <strong>⚠️ Acción requerida:</strong> Este documento requiere tu firma
                                                digital.
                                                Por favor, ingresa al portal para revisar y firmar el documento.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/documents"
                                            style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                            Ver documento
                                        </a>
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
                                Si tienes dudas, contacta al área de Recursos Humanos.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>