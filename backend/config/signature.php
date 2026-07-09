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
    |   'absolute' -> usa x / name_y fijos (mm, origen esquina superior izquierda)
    |                 tomados del sub-array 'sizes.<page_size>' correspondiente.
    |                 Pensado para boletas de formato fijo, calzando el recuadro
    |                 "RECIBÍ CONFORME / TRABAJADOR".
    |   'auto'     -> comportamiento heredado: esquina inferior derecha calculada
    |                 proporcionalmente al tamaño real de la página (no usa
    |                 x/name_y de 'sizes', pero sí width/align/*_font_size).
    |
    | Unidades en milímetros.
    |
    | ---------------------------------------------------------------------
    | Ítem 36 (sprint-fix): selección de tamaño de boleta antes de la carga
    | masiva
    | ---------------------------------------------------------------------
    | Antes de este cambio solo existía UN juego de coordenadas absolutas
    | (las que ahora viven en sizes.a10), calibradas a ojo para el formato de
    | boleta que este cliente ya usaba en producción. Ese formato es un
    | tamaño CUSTOM (no es el ISO A10 real, que mide 26x37mm y sería
    | imposible de usar) — evidencia real tomada de una boleta ya firmada en
    | producción: MediaBox de 595.32 x 419.52 pt = 210 x 148 mm (ancho A4,
    | alto A5 — como una hoja A4 cortada a la mitad). 'a10' es simplemente la
    | etiqueta interna heredada para ESE formato específico.
    |
    | default_size / sizes.a10 mantienen EXACTAMENTE los valores que ya
    | estaban en producción (mismos defaults, mismas env vars) — el
    | comportamiento por defecto NO cambia.
    |
    | sizes.a4 / a5 / letter son NUEVOS y se derivaron proporcionalmente a
    | partir de a10, asumiendo como página de referencia de a10 el tamaño
    | real observado arriba (210 x 148 mm):
    |   fx = x_a10 / 210         (posición horizontal como % del ancho)
    |   fy = name_y_a10 / 148    (posición vertical como % del alto)
    |   fw = width_a10 / 210     (ancho del bloque como % del ancho)
    | y luego fx/fy/fw se aplican al ancho/alto ISO/ANSI estándar de cada
    | tamaño (A4 210x297, A5 148x210, Carta 215.9x279.4 mm, todos en
    | orientación portrait). Los tamaños de fuente y espaciados NO se
    | escalan (se asume que el recuadro de firma impreso mide igual sin
    | importar el tamaño de página) — es la suposición más simple, pero es
    | una suposición. NINGUNO de los 3 tamaños nuevos fue validado
    | visualmente contra una boleta real en ese formato.
    |
    | ⚠️ REQUIERE PRUEBA VISUAL ANTES DE HABILITAR EN PRODUCCIÓN: subir una
    | boleta real en cada tamaño (a4/a5/letter), firmarla (flujo 2FA,
    | SignatureService::verifyAndSign) y verificar a ojo que el nombre/fecha
    | caen dentro del recuadro "RECIBÍ CONFORME". Ajustar los mm aquí sin
    | tocar código si hace falta.
    |
    */
    'watermark' => [
        'mode' => env('SIGNATURE_MODE', 'absolute'),

        // Tamaño usado cuando el batch/documento no tiene page_size
        // asignado (lotes viejos, anteriores al ítem 36).
        'default_size' => 'a10',

        'sizes' => [
            // Formato calibrado en producción (comportamiento por defecto,
            // SIN CAMBIOS respecto al config anterior). Página de
            // referencia real: 210 x 148 mm.
            'a10' => [
                'x' => (float) env('SIGNATURE_X', 137),        // mm desde la izquierda (centro ≈ 165)
                'name_y' => (float) env('SIGNATURE_NAME_Y', 119), // mm desde arriba (nombre, más cerca de la línea)
                'width' => (float) env('SIGNATURE_WIDTH', 56),    // ancho del bloque (mm) — el nombre se auto-ajusta
                'align' => env('SIGNATURE_ALIGN', 'C'),           // C | L | R
                'name_font_size' => (float) env('SIGNATURE_NAME_SIZE', 13),
                'name_height' => (float) env('SIGNATURE_NAME_HEIGHT', 7),  // alto celda nombre (mm)
                'date_offset_y' => (float) env('SIGNATURE_DATE_OFFSET', 5), // positivo = fecha DEBAJO del nombre (sobre la línea)
                'date_font_size' => (float) env('SIGNATURE_DATE_SIZE', 7),
            ],

            // NUEVO — derivado proporcionalmente de a10 (ver nota arriba).
            // Página A4 portrait: 210 x 297 mm.
            'a4' => [
                'x' => 137.0,
                'name_y' => 238.8,
                'width' => 56.0,
                'align' => 'C',
                'name_font_size' => 13,
                'name_height' => 7,
                'date_offset_y' => 5,
                'date_font_size' => 7,
            ],

            // NUEVO — derivado proporcionalmente de a10 (ver nota arriba).
            // Página A5 portrait: 148 x 210 mm.
            'a5' => [
                'x' => 96.55,
                'name_y' => 168.85,
                'width' => 39.47,
                'align' => 'C',
                'name_font_size' => 13,
                'name_height' => 7,
                'date_offset_y' => 5,
                'date_font_size' => 7,
            ],

            // NUEVO — derivado proporcionalmente de a10 (ver nota arriba).
            // Página Carta/Letter portrait: 215.9 x 279.4 mm.
            'letter' => [
                'x' => 140.87,
                'name_y' => 224.69,
                'width' => 57.57,
                'align' => 'C',
                'name_font_size' => 13,
                'name_height' => 7,
                'date_offset_y' => 5,
                'date_font_size' => 7,
            ],
        ],
    ],
];
