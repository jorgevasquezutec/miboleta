<?php

namespace App\Support;

/**
 * Normalización del número de documento (DNI/RUC/CE/pasaporte) que llega de
 * la carga masiva (Excel o JSON editado en el grid).
 *
 * Causa raíz que resuelve (Obs 4): la columna "Número de documento" de la
 * plantilla queda en formato General; el DefaultValueBinder de PhpSpreadsheet
 * numeriza cualquier celda que "parezca número", así que un DNI como
 * "01234567" llega al import como el entero 1234567 (o como el string
 * "1234567.0" cuando la celda se leyó como float). Este helper repone el
 * padding perdido; NO reemplaza el formato de celda (ver
 * BulkUserColumns::TEXT_COLUMNS / UsersSheetTemplate), que evita el problema
 * de raíz para archivos generados por esta plantilla, pero no para archivos
 * de terceros o datos ya pegados en el grid.
 */
final class DocumentNumber
{
    /**
     * Longitud objetivo de padding por tipo de documento (con cero a la
     * izquierda). Los tipos no listados (passport, o un tipo desconocido) no
     * se rellenan: no tienen una longitud fija ni son necesariamente
     * numéricos.
     */
    private const PAD_LENGTHS = [
        'dni' => 8,
        'ruc' => 11,
        'ce' => 12,
    ];

    /**
     * Normalizar un número de documento crudo. null/'' -> null. Castea a
     * string, recorta espacios, limpia el artefacto de Excel que numeriza la
     * celda ("12345678.0" -> "12345678") y, si el resultado son solo dígitos
     * y el tipo de documento tiene una longitud fija conocida (dni/ruc/ce),
     * repone los ceros a la izquierda que esa numerización se comió.
     *
     * Nunca trunca: un valor ya más largo que la longitud objetivo se
     * devuelve tal cual (puede ser un dato inválido, pero eso lo decide la
     * validación, no este helper).
     */
    public static function normalize(?string $tipo, mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        // Artefacto de Excel: una celda en formato General que el usuario
        // tipeó como "12345678" se lee como float y llega aquí como
        // "12345678.0" (o similar, con más ceros tras el punto).
        if (preg_match('/^\d+\.0+$/', $valor)) {
            $valor = substr($valor, 0, (int) strpos($valor, '.'));
        }

        $tipo = $tipo !== null ? strtolower(trim($tipo)) : null;
        $padLength = $tipo !== null ? (self::PAD_LENGTHS[$tipo] ?? null) : null;

        if ($padLength !== null && ctype_digit($valor) && strlen($valor) < $padLength) {
            $valor = str_pad($valor, $padLength, '0', STR_PAD_LEFT);
        }

        return $valor;
    }
}
