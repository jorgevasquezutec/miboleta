<?php

/*
|--------------------------------------------------------------------------
| Guards de la Matriz de Accesos (config/access_matrix.php)
|--------------------------------------------------------------------------
|
| Uso: php scripts/check-access-matrix.php   (desde la raíz del repo)
| Corre en CI (.github/workflows/tests.yml, job "access-matrix-guards").
| Solo necesita PHP: lee el config con `require` (es un array plano) y escanea
| el resto por texto. No arranca Laravel ni requiere composer install.
|
| Guard 1 — fuga de privilegios entre empresas:
|   Falla ante `getCurrentRole()` SIN empresa fuera de la allowlist. Ese método
|   cae al respaldo global `user_roles` (la UNIÓN de los roles del usuario en
|   TODAS sus empresas, con ->first() sin ORDER BY = no determinístico), así que
|   autorizar con él deja que un admin en la empresa A pase checks operando en
|   la B. Para AUTORIZAR se usa $this->authorize($ability, $tenantId).
|
| Guard 2 — deriva frontend↔backend:
|   Falla si el frontend referencia una ability que no existe en el config. Es
|   el bug original: el sidebar mostraba "Auditoría" a un aprobador y el backend
|   respondía 403.
|
*/

// realpath: sin él las rutas quedan como "scripts/../backend/..." y no casan
// contra la allowlist ni contra FRONTEND_SKIP.
define('REPO_ROOT', realpath(__DIR__ . '/..') . '/');

/**
 * Guard 1: usos de getCurrentRole() sin empresa que NO son autorización.
 *
 * Clave = ruta del archivo; valor = por qué se acepta. Agregar una entrada aquí
 * es una decisión consciente: solo vale para DISPLAY/logging (mostrar el rol,
 * no decidir permisos). Si necesitas autorizar, no vengas aquí — usa una
 * ability de la matriz.
 */
const GET_CURRENT_ROLE_ALLOWLIST = [
    'backend/app/Http/Resources/UserResource.php' => 'display: campo "role" de la API',
    'backend/app/Http/Resources/UserSummaryResource.php' => 'display: campo "role" de la API',
    'backend/app/Http/Controllers/Api/UserController.php' => 'display: campo "role" en listados',
    'backend/app/Logging/ContextProcessor.php' => 'logging: contexto user_role',
    'backend/app/Services/AuthService.php' => 'display: payload de /login',
    'backend/app/Services/ProfileService.php' => 'display: payload de /me',
    'backend/app/Services/TenantService.php' => 'display: rol en el listado de miembros',
    'backend/app/Services/ReportsService.php' => 'display: columna "rol" del export',
    'backend/app/Models/User.php' => 'definición del propio método',
    // Riesgo #2 del plan de autorización: el filtrado por FILA del listado de
    // documentos admite varias empresas a la vez (X-Tenant-Ids), y ahí no hay
    // un rol único válido para toda la consulta. Limitación preexistente y
    // documentada en DocumentService::applyRoleFilters. Los checks sobre un
    // documento CONCRETO sí están scopeados a la empresa del documento.
    'backend/app/Services/DocumentService.php' => 'Riesgo #2: filtrado por fila multi-empresa (preexistente)',
];

/** Guard 2: dónde busca el frontend y qué patrones extrae. */
const FRONTEND_DIR = 'src';
const FRONTEND_SKIP = [
    // Define los helpers: sus `can(ability)` son parámetros, no literales.
    'src/presentation/hooks/useCan.ts',
];

$errors = [];

/** Lista recursiva de archivos con alguna de las extensiones dadas. */
function filesIn(string $dir, array $extensions): array
{
    $absolute = REPO_ROOT . $dir;

    if (!is_dir($absolute)) {
        return [];
    }

    $found = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
            // Ruta relativa a la raíz del repo, con separadores normalizados.
            $found[] = str_replace('\\', '/', substr($file->getRealPath(), strlen(REPO_ROOT)));
        }
    }

    sort($found);

    return $found;
}

/** Nº de línea (1-indexado) de un offset de byte dentro del contenido. */
function lineAt(string $contents, int $offset): int
{
    return substr_count($contents, "\n", 0, $offset) + 1;
}

// ── Guard 1 ─────────────────────────────────────────────────────────────────
// `getCurrentRole()` con paréntesis vacíos = sin empresa. Se ignoran comentarios
// (los hay que explican el método) y `function getCurrentRole()`.
foreach (filesIn('backend/app', ['php']) as $file) {
    if (array_key_exists($file, GET_CURRENT_ROLE_ALLOWLIST)) {
        continue;
    }

    $contents = file_get_contents(REPO_ROOT . $file);

    if (!preg_match_all('/getCurrentRole\(\s*\)/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[0] as [$match, $offset]) {
        $line = lineAt($contents, $offset);
        $source = trim(explode("\n", $contents)[$line - 1] ?? '');

        // Comentarios y la declaración del método no autorizan nada.
        if (preg_match('/^\s*(\/\/|\*|\/\*)/', $source) || str_contains($source, 'function getCurrentRole')) {
            continue;
        }

        $errors[] = "{$file}:{$line}\n"
            . "    getCurrentRole() sin empresa: cae al respaldo global (unión de roles de TODAS las\n"
            . "    empresas del usuario) y es una fuga de privilegios entre empresas.\n"
            . "    Para autorizar: \$this->authorize('<ability>', \$tenantId) — ver config/access_matrix.php.\n"
            . "    Si es display/logging (no decide permisos), agrega el archivo a\n"
            . "    GET_CURRENT_ROLE_ALLOWLIST en " . basename(__FILE__) . " con su motivo.";
    }
}

// ── Guard 2 ─────────────────────────────────────────────────────────────────
$matrix = require REPO_ROOT . 'backend/config/access_matrix.php';
$abilities = array_keys($matrix);

// Cada patrón captura el/los literal(es) de ability. Los que agrupan varias
// (useCanAny([...]), requires={[...]}, abilities: [...]) se re-escanean para
// sacar cada string suelto, así que el grupo 1 puede ser una lista entera.
$patterns = [
    '/(?<![\w.])useCan\(\s*([^)]*)\)/',
    '/(?<![\w.])useCanAny\(\s*\[([^\]]*)\]/',
    '/(?<![\w.])canAny\(\s*\[([^\]]*)\]/',
    '/(?<![\w.])can\(\s*([\'"][^\'"]*[\'"])\s*\)/',
    '/requires=\{?\s*\[([^\]]*)\]/',
    '/requires=("[^"]*"|\'[^\']*\')/',
    '/abilities:\s*\[([^\]]*)\]/',
];

foreach (filesIn(FRONTEND_DIR, ['ts', 'tsx']) as $file) {
    if (in_array($file, FRONTEND_SKIP, true)) {
        continue;
    }

    $contents = file_get_contents(REPO_ROOT . $file);

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[1] as $index => [$captured, $_]) {
            // Se validan solo los literales. Una ability calculada en runtime
            // (`useCan(someVar)`) no es verificable aquí; el fail-closed de
            // useCan()/hasAbility() la cubre.
            if (!preg_match_all('/[\'"]([^\'"]+)[\'"]/', $captured, $literals)) {
                continue;
            }

            $line = lineAt($contents, $matches[0][$index][1]);

            foreach ($literals[1] as $ability) {
                if (in_array($ability, $abilities, true)) {
                    continue;
                }

                $errors[] = "{$file}:{$line}\n"
                    . "    Ability \"{$ability}\" no existe en backend/config/access_matrix.php.\n"
                    . "    El frontend gatearía con una ability que el backend desconoce: el menú/ruta\n"
                    . "    se mostraría y la API respondería 403 (el bug original de la Matriz de Accesos).\n"
                    . "    Corrige el nombre o agrega la ability al config (queda registrada como Gate\n"
                    . "    y expuesta al frontend automáticamente).";
            }
        }
    }
}

// ── Salida ──────────────────────────────────────────────────────────────────
if ($errors !== []) {
    $count = count($errors);
    fwrite(STDERR, "\n✗ Matriz de Accesos: {$count} problema(s)\n\n");
    fwrite(STDERR, implode("\n\n", $errors) . "\n\n");
    exit(1);
}

$abilityCount = count($abilities);
echo "✓ Matriz de Accesos: sin getCurrentRole() sin empresa fuera de la allowlist; "
    . "referencias del frontend válidas contra las {$abilityCount} abilities del config.\n";
exit(0);
