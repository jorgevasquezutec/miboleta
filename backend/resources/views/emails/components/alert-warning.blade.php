{{--
Email Alert Component (Yellow/Orange warning style)
Usage: @include('emails.components.alert-warning', ['message' => 'Warning text', 'icon' => '⚠️'])
--}}
<div
    style="background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
    <p style="color: #92400E; font-size: 14px; margin: 0;">
        @if(isset($icon))<strong>{{ $icon }} </strong>@endif{!! $message !!}
    </p>
</div>