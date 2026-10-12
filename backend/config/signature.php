<?php

// Media hoja A4 horizontal (210 x 148 mm): coordenadas calibradas con boletas
// reales en producción (antes 'a10', ahora 'a5'). Mismas env vars de siempre.
$mediaHoja = [
    'x' => (float) env('SIGNATURE_X', 137),        // mm desde la izquierda (centro ≈ 165)
    'name_y' => (float) env('SIGNATURE_NAME_Y', 119), // mm desde arriba (nombre, más cerca de la línea)
    'width' => (float) env('SIGNATURE_WIDTH', 56),    // ancho del bloque (mm) — el nombre se auto-ajusta
    'align' => env('SIGNATURE_ALIGN', 'C'),           // C | L | R
    'name_font_size' => (float) env('SIGNATURE_NAME_SIZE', 16),
    'name_height' => (float) env('SIGNATURE_NAME_HEIGHT', 7),  // alto celda nombre (mm)
    'date_offset_y' => (float) env('SIGNATURE_DATE_OFFSET', 5), // positivo = fecha DEBAJO del nombre (sobre la línea)
    'date_font_size' => (float) env('SIGNATURE_DATE_SIZE', 8),
    // Dentro de la boleta, abajo a la derecha (ver 'placement' más abajo).
    'placement' => 'document',
];

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
    | ---------------------------------------------------------------------
    | Formatos vigentes: A4, A5 y Carta (a10 ya no se ofrece)
    | ---------------------------------------------------------------------
    | Por pedido del cliente solo se cargan lotes A4, A5 o Carta, con la
    | conformidad del trabajador ABAJO A LA DERECHA y el sello de la empresa
    | abajo a la izquierda (ver visible_stamp_position).
    |
    | placement (por formato):
    |   'footer'   -> con el sello abajo a la izquierda, la conformidad va en
    |                 la MISMA franja del sello, a la derecha y a su altura
    |                 (A4 y Carta). x/name_y solo se usan sin sello izquierdo
    |                 (p. ej. el flujo FPDI, sin certificado).
    |   'document' -> siempre en x/name_y, dentro del documento (A5).
    |
    | a5 = MEDIA HOJA A4 HORIZONTAL (210 x 148 mm), lo que antes se llamaba
    | 'a10': mismas coordenadas, calibradas con boletas reales. 'a10' se
    | conserva solo para los lotes viejos que lo tienen guardado.
    |
    | El formato se detecta por el tamaño REAL de la página; el page_size del
    | lote solo decide si el tamaño no calza con ninguno de page_dimensions_mm.
    |
    */
    'watermark' => [
        'mode' => env('SIGNATURE_MODE', 'absolute'),

        // Tamaño usado cuando el lote no eligió page_size y el tamaño real de
        // la página no calza con ninguno de page_dimensions_mm.
        'default_size' => 'a4',

        // Dimensiones reales [ancho, alto] en mm de cada formato. ÚNICA fuente
        // para detectar el formato por el tamaño real de la página
        // (SignatureLayout::detectSizeKey y el sidecar), que tiene prioridad
        // sobre el page_size del lote. Comparación por ancho/alto con
        // tolerancia. 'a5' es la media hoja HORIZONTAL (210 x 148).
        'page_dimensions_mm' => [
            'a4' => [210.0, 297.0],
            'a5' => [210.0, 148.0],
            'letter' => [215.9, 279.4],
        ],

        // Tolerancia de la detección: fracción (3%) o ±5 mm, lo que sea mayor.
        'detect_tolerance_ratio' => 0.03,
        'detect_tolerance_mm' => 5.0,

        'sizes' => [
            // Nombre anterior de la media hoja; ya no se ofrece al cargar. Se
            // conserva para los lotes viejos que tienen 'a10' guardado.
            'a10' => $mediaHoja,

            // Página A4 portrait: 210 x 297 mm. Conformidad en la franja del
            // sello; x/name_y (sin sello izquierdo) calibrados con boleta real
            // de 2 copias por hoja: "RECIBÍ CONFORME" de la copia de abajo.
            'a4' => [
                'x' => 137.0,
                'name_y' => 268.5,
                'width' => 56.0,
                'align' => 'C',
                'name_font_size' => 16,
                'name_height' => 7,
                'date_offset_y' => 5,
                'date_font_size' => 8,
                'placement' => 'footer',
            ],

            // Media hoja A4 horizontal: 210 x 148 mm. Dentro de la boleta,
            // abajo a la derecha (coordenadas calibradas del antiguo a10).
            'a5' => $mediaHoja,

            // Página Carta/Letter portrait: 215.9 x 279.4 mm. Conformidad en
            // la franja del sello; x/name_y (sin sello izquierdo) abajo a la
            // derecha con los márgenes de a4 (sin validar con boleta real).
            'letter' => [
                'x' => 142.9,
                'name_y' => 250.9,
                'width' => 56.0,
                'align' => 'C',
                'name_font_size' => 16,
                'name_height' => 7,
                'date_offset_y' => 5,
                'date_font_size' => 8,
                'placement' => 'footer',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sello visible de la firma con certificado (PAdES)
    |--------------------------------------------------------------------------
    |
    | Si es true, el sidecar dibuja un sello visible con los datos del
    | certificado. Por pedido del cliente ahora va visible (antes era invisible)
    | y en la parte inferior izquierda: "FIRMADO DIGITALMENTE", titular, RUC y
    | fecha (la conformidad del trabajador va abajo a la derecha).
    |
    | visible_stamp_position:
    |   'bottom_left'   -> sello compacto abajo a la izquierda (por defecto). En
    |                      los formatos con placement 'footer' (A4, Carta) la
    |                      conformidad va en la MISMA franja, a la derecha y a la
    |                      altura del sello; en A5 va dentro de la boleta.
    |   'bottom_center' -> el mismo sello compacto, abajo al centro.
    |   'footer'    -> sello ancho heredado en el pie de la última página, con
    |                  representante, razón social, RUC, cargo, país/provincia y
    |                  fecha; alarga la página para no tapar contenido.
    |
    | Solo aplica cuando visible_stamp es true. Un valor inválido cae a
    | 'bottom_left'.
    |
    */
    'visible_stamp' => (bool) env('SIGNATURE_VISIBLE_STAMP', true),
    'visible_stamp_position' => env('SIGNATURE_VISIBLE_STAMP_POSITION', 'bottom_left'),
];
