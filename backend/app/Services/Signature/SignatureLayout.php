<?php

namespace App\Services\Signature;

/**
 * Detección del formato de boleta (a4|a5|a10|letter) por el tamaño REAL de la
 * página, para cuando el lote no eligió page_size explícito. Las dimensiones
 * viven en config('signature.watermark.page_dimensions_mm').
 */
class SignatureLayout
{
    /**
     * @return string key de config('signature.watermark.sizes'); si el tamaño no
     *                calza con ninguno, default_size ('a10').
     */
    public static function detectSizeKey(float $wMm, float $hMm): string
    {
        $default = config('signature.watermark.default_size', 'a10');
        $ratio = (float) config('signature.watermark.detect_tolerance_ratio', 0.03);
        $absMm = (float) config('signature.watermark.detect_tolerance_mm', 5.0);

        $best = null;
        $bestErr = INF;
        foreach (config('signature.watermark.page_dimensions_mm', []) as $key => [$w, $h]) {
            $tolW = max($w * $ratio, $absMm);
            $tolH = max($h * $ratio, $absMm);
            $dw = abs($wMm - $w);
            $dh = abs($hMm - $h);
            if ($dw <= $tolW && $dh <= $tolH) {
                $err = $dw / $w + $dh / $h;
                if ($err < $bestErr) {
                    $best = $key;
                    $bestErr = $err;
                }
            }
        }

        return $best ?? $default;
    }

    /**
     * Layout del sidecar (contrato JSON de conformity.layout) para una key.
     */
    public static function sidecarLayout(string $key): array
    {
        $sizes = config('signature.watermark.sizes', []);
        $default = config('signature.watermark.default_size', 'a10');
        $cfg = $sizes[$key] ?? $sizes[$default] ?? [];

        return [
            'mode' => config('signature.watermark.mode', 'absolute'),
            'x_mm' => isset($cfg['x']) ? (float) $cfg['x'] : null,
            'name_y_mm' => isset($cfg['name_y']) ? (float) $cfg['name_y'] : null,
            'width_mm' => (float) ($cfg['width'] ?? 50),
            'align' => $cfg['align'] ?? 'C',
            'name_font_size' => (float) ($cfg['name_font_size'] ?? 12),
            'name_height_mm' => (float) ($cfg['name_height'] ?? 8),
            'date_offset_y_mm' => (float) ($cfg['date_offset_y'] ?? 8),
            'date_font_size' => (float) ($cfg['date_font_size'] ?? 7),
        ];
    }
}
