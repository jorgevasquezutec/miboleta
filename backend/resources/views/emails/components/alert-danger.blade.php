{{--
Email Alert Component (Red danger style)
Usage: @include('emails.components.alert-danger', ['message' => 'Danger text', 'icon' => '🛡️'])
--}}
<div
    style="background: #FEF2F2; border-left: 4px solid #EF4444; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
    <p style="color: #991B1B; font-size: 14px; margin: 0; line-height: 1.5;">
        @if(isset($icon))<strong>{{ $icon }} </strong>@endif{!! $message !!}
    </p>
</div>