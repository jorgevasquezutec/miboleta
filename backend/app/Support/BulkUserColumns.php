<?php

namespace App\Support;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Fuente única de verdad del formato de columnas de la carga masiva de
 * usuarios.
 *
 * La plantilla que se descarga muestra ENCABEZADOS LEGIBLES ("Número de
 * documento", "Rol en empresa 1"), pero el importador trabaja internamente
 * con CLAVES CANÓNICAS en snake_case ('numero_documento', 'org1_rol'). Esta
 * clase mantiene ambas caras sincronizadas:
 *
 * - keys()/labels()/widths(): las usa UsersSheetTemplate para escribir la hoja.
 * - letterFor(): la usa ValidationRulesSheet para colgar los dropdowns de la
 *   columna correcta sin recalcular offsets a mano.
 * - canonicalKey(): la usa UsersImport para traducir el encabezado que venga
 *   en el archivo subido (legible, canónico o un alias de la plantilla del
 *   cliente) a la clave canónica.
 *
 * No existe una columna 'rol' de nivel de fila: el rol es SIEMPRE por empresa
 * (org{n}_rol -> user_tenant_roles). 'root' es un rol global que no se asigna
 * por empresa y no se da de alta por carga masiva (solo un root puede crear
 * otro root, desde el alta individual).
 */
final class BulkUserColumns
{
    /** Tope de organizaciones por fila que admite la plantilla. */
    public const MAX_ORGANIZATIONS = 5;

    /** Tope de filas de usuarios del archivo (hasta dónde llegan formatos y validaciones). */
    public const MAX_USER_ROWS = 1000;

    /**
     * Columnas que contienen fechas. Se escriben como fecha real de Excel
     * (número de serie + formato 'yyyy-mm-dd'), NO como texto: el value binder
     * por defecto de Laravel Excel no interpreta un string 'Y-m-d' como fecha,
     * y una celda con formato de fecha es además lo que hace que
     * Excel/LibreOffice conviertan a fecha lo que el usuario tipea, en vez de
     * dejarlo como texto. Se listan por sufijo (ver widthKey).
     */
    private const DATE_COLUMNS = ['fecha_ingreso', 'fecha_nacimiento'];

    /**
     * Columnas que deben forzarse a TEXTO en el xlsx (nunca numerizadas): el
     * número de documento pierde los ceros a la izquierda si Excel lo
     * interpreta como número (ver App\Support\DocumentNumber, que repone el
     * padding aguas abajo como red de seguridad), y el teléfono puede
     * empezar con '+' o '0'. Se listan por sufijo (ver widthKey), igual que
     * DATE_COLUMNS.
     */
    private const TEXT_COLUMNS = ['numero_documento', 'telefono'];

    /**
     * Columnas de datos del usuario, en orden. clave canónica => encabezado.
     */
    private const BASE = [
        'nombre' => 'Nombre',
        'apellido' => 'Apellidos',
        'email' => 'Correo electrónico',
        'tipo_documento' => 'Tipo de documento',
        'numero_documento' => 'Número de documento',
        'estado' => 'Estado',
        'telefono' => 'Teléfono',
    ];

    /**
     * Columnas por organización, agrupadas en los bloques históricos en que
     * se fueron agregando. El orden de los bloques se conserva a propósito:
     * primero ruc/supervisor de TODAS las organizaciones, luego
     * rol/fecha/saldo de todas, etc. Cada entrada es sufijo => encabezado,
     * donde {n} es el número de organización.
     *
     * @var array<int, array<string, string>>
     */
    private const ORG_BLOCKS = [
        [
            'ruc' => 'RUC empresa {n}',
            'supervisor_email' => 'Supervisor empresa {n} (correo)',
        ],
        [
            'rol' => 'Rol en empresa {n}',
            'fecha_ingreso' => 'Fecha de ingreso empresa {n}',
            'saldo_vacaciones' => 'Saldo de vacaciones empresa {n}',
        ],
        [
            'departamento' => 'Departamento empresa {n}',
            'cargo' => 'Cargo empresa {n}',
        ],
    ];

    /**
     * Columnas de usuario que van al final de la hoja (después de los bloques
     * por organización). clave canónica => encabezado.
     */
    private const TRAILING = [
        'fecha_nacimiento' => 'Fecha de nacimiento',
    ];

    /** Ancho de columna por clave canónica (sufijo, para las de organización). */
    private const WIDTHS = [
        'nombre' => 18,
        'apellido' => 22,
        'email' => 32,
        'tipo_documento' => 20,
        'numero_documento' => 22,
        'estado' => 12,
        'telefono' => 18,
        'fecha_nacimiento' => 22,
        'ruc' => 20,
        'supervisor_email' => 32,
        'rol' => 22,
        'fecha_ingreso' => 26,
        'saldo_vacaciones' => 26,
        'departamento' => 24,
        'cargo' => 24,
    ];

    /**
     * Alias adicionales de encabezado (ya normalizados) => clave canónica.
     * Cubre la plantilla del cliente (APEPAT, TIPDOC, DOCIDEN...) y los
     * encabezados canónicos antiguos que no coinciden con el slug del nuevo
     * encabezado legible. Ver docs/sprintfix/MAPEO-CARGA-MASIVA.md.
     *
     * Los alias que necesitan resolverse contra la BD o combinar varias
     * columnas (apepat+apemat, organizacion -> RUC) NO viven aquí: los
     * resuelve UsersImport::normalizeRow, que corre después de esta traducción.
     */
    private const EXTRA_ALIASES = [
        'apellidos' => 'apellido',
        'correo' => 'email',
        'correo_electronico' => 'email',
        'tipo_de_documento' => 'tipo_documento',
        'numero_de_documento' => 'numero_documento',
        'nro_documento' => 'numero_documento',
        'dociden' => 'numero_documento',
        'tipdoc' => 'tipo_documento',
        'fecha_de_nacimiento' => 'fecha_nacimiento',
        'birth_date' => 'fecha_nacimiento',
        'fecnac' => 'fecha_nacimiento',
        'fec_nacimiento' => 'fecha_nacimiento',
        'celular' => 'telefono',
    ];

    /**
     * Claves canónicas de la hoja, en el orden en que aparecen las columnas.
     *
     * @return list<string>
     */
    public static function keys(int $maxOrganizations): array
    {
        return array_keys(self::map($maxOrganizations));
    }

    /**
     * Encabezados legibles de la hoja, en orden.
     *
     * @return list<string>
     */
    public static function labels(int $maxOrganizations): array
    {
        return array_values(self::map($maxOrganizations));
    }

    /**
     * Anchos de columna, indexados por letra de columna ('A' => 18, ...),
     * en el formato que espera WithColumnWidths.
     *
     * @return array<string, int>
     */
    public static function widths(int $maxOrganizations): array
    {
        $widths = [];
        $index = 1;

        foreach (array_keys(self::map($maxOrganizations)) as $key) {
            $letter = Coordinate::stringFromColumnIndex($index++);
            $widths[$letter] = self::WIDTHS[self::widthKey($key)] ?? 20;
        }

        return $widths;
    }

    /**
     * Letra de columna de una clave canónica ('estado' => 'F',
     * 'org2_rol' => ...). Lanza si la clave no pertenece a la plantilla,
     * para que un typo falle de inmediato y no cuelgue una validación en la
     * columna equivocada.
     */
    public static function letterFor(string $key, int $maxOrganizations): string
    {
        $index = array_search($key, self::keys($maxOrganizations), true);

        if ($index === false) {
            throw new \InvalidArgumentException(
                "Columna desconocida en la plantilla de carga masiva: '{$key}'."
            );
        }

        return Coordinate::stringFromColumnIndex($index + 1);
    }

    /**
     * ¿Esta columna contiene fechas? (ver DATE_COLUMNS)
     */
    public static function isDateKey(string $key): bool
    {
        return in_array(self::widthKey($key), self::DATE_COLUMNS, true);
    }

    /**
     * Claves canónicas de las columnas de fecha de la hoja, en orden.
     *
     * @return list<string>
     */
    public static function dateKeys(int $maxOrganizations): array
    {
        return array_values(array_filter(
            self::keys($maxOrganizations),
            static fn(string $key) => self::isDateKey($key)
        ));
    }

    /**
     * ¿Esta columna debe forzarse a texto en el xlsx? (ver TEXT_COLUMNS)
     */
    public static function isTextKey(string $key): bool
    {
        return in_array(self::widthKey($key), self::TEXT_COLUMNS, true);
    }

    /**
     * Claves canónicas de las columnas de texto forzado de la hoja, en orden.
     *
     * @return list<string>
     */
    public static function textKeys(int $maxOrganizations): array
    {
        return array_values(array_filter(
            self::keys($maxOrganizations),
            static fn(string $key) => self::isTextKey($key)
        ));
    }

    /**
     * Traducir un encabezado tal como viene en el archivo subido a su clave
     * canónica. Acepta el encabezado legible de la plantilla ("Número de
     * documento"), la clave canónica ('numero_documento') y los alias
     * conocidos ('DOCIDEN'). Si no reconoce nada, devuelve el encabezado
     * normalizado tal cual, para que la validación lo reporte como columna
     * faltante en vez de reventar.
     */
    public static function canonicalKey(string $rawHeader): string
    {
        $normalized = self::normalizeHeaderKey($rawHeader);

        return self::aliases()[$normalized] ?? $normalized;
    }

    /**
     * Normalizar un encabezado: minúsculas, sin tildes/diacríticos y
     * separadores no alfanuméricos colapsados a guion bajo. Replica lo que
     * hace el heading row formatter 'slug' de Laravel Excel, de forma
     * defensiva por si no estuviera activo.
     */
    public static function normalizeHeaderKey(string $key): string
    {
        $key = Str::ascii(trim($key));
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return trim($key, '_');
    }

    /**
     * Encabezado legible de una clave canónica; se usa en los mensajes de
     * error para nombrar la columna igual que la ve el usuario en el Excel.
     */
    public static function labelFor(string $key): string
    {
        $map = self::map(self::MAX_ORGANIZATIONS);

        return $map[$key] ?? $key;
    }

    /**
     * Mapa completo clave canónica => encabezado, en orden de columna.
     *
     * @return array<string, string>
     */
    private static function map(int $maxOrganizations): array
    {
        $maxOrganizations = max(1, min($maxOrganizations, self::MAX_ORGANIZATIONS));

        $map = self::BASE;

        foreach (self::ORG_BLOCKS as $block) {
            for ($n = 1; $n <= $maxOrganizations; $n++) {
                foreach ($block as $suffix => $label) {
                    $map["org{$n}_{$suffix}"] = str_replace('{n}', (string) $n, $label);
                }
            }
        }

        return $map + self::TRAILING;
    }

    /**
     * Alias normalizado => clave canónica. Incluye el slug de cada
     * encabezado legible, la identidad de cada clave canónica y los alias
     * extra. Se calcula siempre con el máximo de organizaciones, para
     * aceptar un archivo generado con una plantilla más ancha que la actual.
     *
     * @return array<string, string>
     */
    private static function aliases(): array
    {
        static $aliases = null;

        if ($aliases !== null) {
            return $aliases;
        }

        $aliases = [];

        foreach (self::map(self::MAX_ORGANIZATIONS) as $key => $label) {
            $aliases[$key] = $key;
            $aliases[self::normalizeHeaderKey($label)] = $key;
        }

        // 'org{n}_roles' (plural) es un alias histórico de 'org{n}_rol'.
        for ($n = 1; $n <= self::MAX_ORGANIZATIONS; $n++) {
            $aliases["org{$n}_roles"] = "org{$n}_rol";
        }

        return $aliases = array_merge($aliases, self::EXTRA_ALIASES);
    }

    /**
     * Clave con la que buscar el ancho: para columnas de organización se usa
     * el sufijo ('org2_rol' => 'rol'), porque el ancho no depende del número.
     */
    private static function widthKey(string $key): string
    {
        if (preg_match('/^org\d+_(.+)$/', $key, $matches)) {
            return $matches[1];
        }

        return $key;
    }
}
