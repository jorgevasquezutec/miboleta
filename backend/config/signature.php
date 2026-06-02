<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Posición y tamaño de la firma estampada en el PDF (watermark)
    |--------------------------------------------------------------------------
    |
    | Controla cómo PdfWatermarkService dibuja el nombre del firmante (cursivo)
    | y el sello de tiempo sobre la boleta. Solo texto, sin fondo (transparente).
    |
    | mode:
    |   'absolute' -> usa x / name_y fijos (mm, origen esquina superior izquierda).
    |                 Pensado para boletas de formato fijo, calzando el recuadro
    |                 "RECIBÍ CONFORME / TRABAJADOR".
    |   'auto'     -> comportamiento heredado: esquina inferior derecha calculada.
    |
    | Unidades en milímetros (A4 = 210 x 297 mm). Ajustar aquí sin tocar código.
    |
    */
    'watermark' => [
        'mode' => env('SIGNATURE_MODE', 'absolute'),

        // Bloque de firma (modo absolute). Centro horizontal = x + width/2.
        'x' => (float) env('SIGNATURE_X', 137),        // mm desde la izquierda (centro ≈ 165)
        'name_y' => (float) env('SIGNATURE_NAME_Y', 119), // mm desde arriba (nombre, más cerca de la línea)
        'width' => (float) env('SIGNATURE_WIDTH', 56),    // ancho del bloque (mm) — el nombre se auto-ajusta
        'align' => env('SIGNATURE_ALIGN', 'C'),           // C | L | R

        // Tamaños y espaciado. El nombre se auto-reduce si no cabe en 'width'.
        'name_font_size' => (float) env('SIGNATURE_NAME_SIZE', 13),
        'name_height' => (float) env('SIGNATURE_NAME_HEIGHT', 7),  // alto celda nombre (mm)
        'date_offset_y' => (float) env('SIGNATURE_DATE_OFFSET', 5), // positivo = fecha DEBAJO del nombre (sobre la línea)
        'date_font_size' => (float) env('SIGNATURE_DATE_SIZE', 7),
    ],
];
