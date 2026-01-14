{{--
Email Button Component
Usage: @include('emails.components.button', ['url' => $url, 'text' => 'Click me'])
Optional: @include('emails.components.button', ['url' => $url, 'text' => 'Click me', 'showFallback' => false])
--}}
<div style="text-align: center; margin: 30px 0;">
    <a href="{{ $url }}"
        style="display: inline-block; background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: #FFFFFF; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);">
        {{ $text }}
    </a>
</div>

@if(!isset($showFallback) || $showFallback !== false)
    <p style="color: #94A3B8; font-size: 12px; text-align: center; margin: 10px 0 20px 0; line-height: 1.5;">
        Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:<br>
        <a href="{{ $url }}" style="color: #3B82F6; word-break: break-all;">{{ $url }}</a>
    </p>
@endif