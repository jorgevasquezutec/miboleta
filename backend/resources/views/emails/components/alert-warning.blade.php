{{--
Email Alert Component (Yellow/Orange warning style) - Compatible con Outlook, Gmail, Apple Mail
Usage: @include('emails.components.alert-warning', ['message' => 'Warning text', 'icon' => '⚠️'])
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
    <tr>
        <td bgcolor="#FEF3C7" style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px;">
            <p style="color: #92400E; font-size: 14px; margin: 0;">
                @if(isset($icon))<strong>{{ $icon }} </strong>@endif{!! $message !!}
            </p>
        </td>
    </tr>
</table>
