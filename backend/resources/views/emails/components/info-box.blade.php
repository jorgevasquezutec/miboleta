{{--
Email Info Box Component (neutral gray background) - Compatible con Outlook, Gmail, Apple Mail
Usage: @include('emails.components.info-box', ['content' => 'Some info text'])
--}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
    <tr>
        <td bgcolor="#F8FAFC" style="background-color: #F8FAFC; border-radius: 8px; padding: 20px; text-align: center;">
            {!! $content !!}
        </td>
    </tr>
</table>
