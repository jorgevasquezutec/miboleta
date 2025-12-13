{{--
Email Code Box Component (for displaying codes/passwords)
Usage: @include('emails.components.code-box', ['label' => 'Tu código', 'code' => '123456'])
--}}
<div
    style="background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); border: 2px solid #BFDBFE; border-radius: 12px; padding: 24px; text-align: center; margin-bottom: 24px;">
    @if(isset($label))
        <p style="color: #64748B; font-size: 14px; margin: 0 0 12px 0;">
            {{ $label }}
        </p>
    @endif
    <p
        style="font-family: 'Courier New', monospace; background: #FFFFFF; color: #2563EB; padding: 16px 24px; border-radius: 8px; font-size: 22px; font-weight: 700; letter-spacing: 3px; display: inline-block; border: 1px solid #E2E8F0; margin: 0;">
        {{ $code }}
    </p>
</div>