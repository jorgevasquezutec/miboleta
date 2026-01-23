{{--
Email Code Box Component (for displaying codes/passwords) - Compatible con Outlook, Gmail, Apple Mail
Usage: @include('emails.components.code-box', ['label' => 'Tu código', 'code' => '123456'])
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
    <tr>
        <td bgcolor="#DBEAFE" style="background-color: #DBEAFE; background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center;">
            @if(isset($label))
                <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">
                    {{ $label }}
                </p>
            @endif
            <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                <tr>
                    <td bgcolor="#FFFFFF" style="background-color: #FFFFFF; padding: 16px 24px; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <p style="font-family: 'Courier New', monospace; color: #2563EB; font-size: 22px; font-weight: 700; letter-spacing: 3px; margin: 0;">
                            {{ $code }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
