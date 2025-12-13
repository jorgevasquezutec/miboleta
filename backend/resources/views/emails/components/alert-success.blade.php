{{--
Email Alert Component (Green success style)
Usage: @include('emails.components.alert-success', ['message' => 'Success text', 'icon' => '💡'])
--}}
<div
    style="background: #ECFDF5; border-left: 4px solid #10B981; border-radius: 0 8px 8px 0; padding: 16px; margin-bottom: 30px;">
    <p style="color: #065F46; font-size: 14px; margin: 0;">
        @if(isset($icon))<strong>{{ $icon }} </strong>@endif{!! $message !!}
    </p>
</div>