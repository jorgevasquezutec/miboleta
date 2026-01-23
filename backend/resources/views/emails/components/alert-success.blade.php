{{--
Email Alert Component (Green success style) - Compatible con Outlook, Gmail, Apple Mail
Usage: @include('emails.components.alert-success', ['message' => 'Success text', 'icon' => '💡'])
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
    <tr>
        <td bgcolor="#ECFDF5" style="background-color: #ECFDF5; border-left: 4px solid #10B981; border-radius: 0 8px 8px 0; padding: 16px;">
            <p style="color: #065F46; font-size: 14px; margin: 0;">
                @if(isset($icon))<strong>{{ $icon }} </strong>@endif{!! $message !!}
            </p>
        </td>
    </tr>
</table>
