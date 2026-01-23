{{--
Email Button Component - Compatible con Outlook, Gmail, Apple Mail
Usage: @include('emails.components.button', ['url' => $url, 'text' => 'Click me'])
Optional: @include('emails.components.button', ['url' => $url, 'text' => 'Click me', 'showFallback' => false])
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
    <tr>
        <td align="center">
            <table cellpadding="0" cellspacing="0" bgcolor="#EFF6FF"
                style="background-color: #EFF6FF; border: 2px solid #2563EB; border-radius: 8px;">
                <tr>
                    <td style="padding: 16px 40px;">
                        <a href="{{ $url }}"
                            style="color: #1E40AF; text-decoration: none; font-size: 16px; font-weight: 600; display: inline-block;">
                            {{ $text }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if(!isset($showFallback) || $showFallback !== false)
    <p style="color: #94A3B8; font-size: 12px; text-align: center; margin: 10px 0 20px 0; line-height: 1.5;">
        Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:<br>
        <a href="{{ $url }}" style="color: #3B82F6; word-break: break-all;">{{ $url }}</a>
    </p>
@endif
