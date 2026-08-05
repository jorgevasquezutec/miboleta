<?php

/*
|--------------------------------------------------------------------------
| Generador de la matriz de accesos para la documentación
|--------------------------------------------------------------------------
|
| Uso: php scripts/generate-access-matrix-doc.php [--check]
|
| Emite por stdout la tabla Markdown de "qué puede hacer cada rol", leída de
| backend/config/access_matrix.php, que es la fuente única de verdad de
| autorización (transcrita del Excel del cliente).
|
| Existe porque el manual de usuario documentaba 3 roles cuando el sistema
| tiene 5, y su matriz contradecía al código en varias filas: una tabla escrita
| a mano se desincroniza en cuanto cambia un permiso. Generándola desde el
| config, la documentación no puede mentir.
|
| Con --check no imprime nada y devuelve código 1 si docs/USER-MANUAL.md tiene
| la tabla desactualizada, para poder engancharlo a CI junto a
| check-access-matrix.php.
|
| Solo necesita PHP: el config es un array plano, se lee con require. No
| arranca Laravel ni requiere composer install.
|
*/

define('REPO_ROOT', realpath(__DIR__ . '/..') . '/');

const MARCA_INICIO = '<!-- MATRIZ-ACCESOS:INICIO -->';
const MARCA_FIN = '<!-- MATRIZ-ACCESOS:FIN -->';

/** Orden y nombre visible de los roles (RoleSeeder::run). */
const ROLES = [
    'root' => 'Super Administrador',
    'admin_tenant' => 'Admin Clientes',
    'admin' => 'Admin Empleados',
    'aprobador' => 'Aprobador',
    'client' => 'Empleado',
];

/** Nombre legible de cada ability, para que la tabla se lea sin saber el slug. */
const ETIQUETAS = [
    'tenants.manage' => 'Crear, editar y desactivar empresas',
    'tenants.view' => 'Consultar las empresas que administra (solo lectura)',
    'tenants.assign_users' => 'Asignar usuarios a una empresa',
    'tenants.update_settings' => 'Configurar una empresa (incluido su SMTP)',
    'users.create_any_role' => 'Crear usuarios con cualquier rol',
    'users.create_limited_role' => 'Crear usuarios con rol aprobador o empleado',
    'users.view_list' => 'Ver el listado de usuarios',
    'users.update' => 'Editar usuarios',
    'users.deactivate' => 'Desactivar usuarios',
    'users.delete' => 'Eliminar usuarios',
    'users.reset_password' => 'Restablecer contraseñas',
    'users.bulk_upload' => 'Carga masiva de usuarios',
    'users.export' => 'Exportar el listado de empleados',
    'reports.app_accounts_export' => 'Exportar el listado de cuentas de aplicación',
    'documents.view_all_multi_tenant' => 'Ver documentos de todas las empresas',
    'documents.view_org' => 'Ver documentos de su empresa',
    'documents.view_own' => 'Ver sus propios documentos',
    'documents.download_own' => 'Descargar sus propios documentos',
    'documents.bulk_upload_zip' => 'Carga masiva de documentos (ZIP)',
    'documents.view_batches' => 'Ver lotes de carga',
    'documents.export' => 'Exportar documentos',
    'documents.sign_2fa' => 'Firmar un documento propio (código por correo)',
    'documents.sign_digital' => 'Firma digital PAdES con certificado de plataforma',
    'documents.delete' => 'Eliminar documentos',
    'documents.view_orphans' => 'Ver documentos huérfanos',
    'documents.assign_orphan' => 'Asignar un documento huérfano a un empleado',
    'vacations.request_own' => 'Solicitar sus vacaciones',
    'vacations.view_own_requests' => 'Ver sus solicitudes',
    'vacations.cancel_own_pending' => 'Cancelar una solicitud propia pendiente',
    'vacations.approve_reject_team' => 'Aprobar o rechazar vacaciones del equipo',
    'vacations.confirm_preapproved' => 'Confirmar si unas vacaciones se tomaron',
    'vacations.view_team_calendar' => 'Ver el calendario del equipo',
    'vacations.view_history' => 'Ver el histórico de vacaciones',
    'audit.view' => 'Ver el registro de auditoría',
    'audit.export' => 'Exportar el registro de auditoría',
    'dashboard.global_metrics' => 'Métricas globales de la plataforma',
    'dashboard.org_metrics' => 'Métricas de su empresa',
    'dashboard.own_summary' => 'Su resumen personal',
    'platform.manage' => 'Ajustes de plataforma (certificado, SMTP, auditoría)',
];

/** Agrupación por módulo, en el orden en que se presentan al usuario. */
const SECCIONES = [
    'Empresas' => [
        'tenants.manage', 'tenants.view', 'tenants.assign_users', 'tenants.update_settings',
    ],
    // Los dos exports van aquí y no en una sección propia porque lo que
    // descargan es el listado de usuarios: separarlos obligaría al lector a
    // buscar en dos sitios quién puede sacar datos de personas.
    'Usuarios' => [
        'users.view_list', 'users.create_any_role', 'users.create_limited_role',
        'users.update', 'users.deactivate', 'users.delete', 'users.reset_password',
        'users.bulk_upload', 'users.export', 'reports.app_accounts_export',
    ],
    'Documentos' => [
        'documents.view_all_multi_tenant', 'documents.view_org', 'documents.view_own',
        'documents.download_own', 'documents.bulk_upload_zip', 'documents.view_batches',
        'documents.export', 'documents.sign_2fa', 'documents.sign_digital',
        'documents.delete', 'documents.view_orphans', 'documents.assign_orphan',
    ],
    'Vacaciones' => [
        'vacations.request_own', 'vacations.view_own_requests', 'vacations.cancel_own_pending',
        'vacations.approve_reject_team', 'vacations.confirm_preapproved',
        'vacations.view_team_calendar', 'vacations.view_history',
    ],
    'Auditoría' => ['audit.view', 'audit.export'],
    'Paneles' => ['dashboard.global_metrics', 'dashboard.org_metrics', 'dashboard.own_summary'],
    'Plataforma' => ['platform.manage'],
];

$matriz = require REPO_ROOT . 'backend/config/access_matrix.php';

// Ninguna ability puede quedarse fuera de la tabla sin que nos enteremos: si
// se añade una al config y no se clasifica aquí, el generador avisa en vez de
// producir una documentación incompleta en silencio.
$clasificadas = array_merge(...array_values(SECCIONES));
$huerfanas = array_diff(array_keys($matriz), $clasificadas);
if ($huerfanas) {
    fwrite(STDERR, "ERROR: abilities sin clasificar en SECCIONES:\n  - "
        . implode("\n  - ", $huerfanas) . "\n"
        . "Añádelas a SECCIONES y ETIQUETAS en " . basename(__FILE__) . "\n");
    exit(1);
}

$salida = [];
$salida[] = MARCA_INICIO;
$salida[] = '';
$salida[] = '> Tabla generada desde `backend/config/access_matrix.php` con';
$salida[] = '> `php scripts/generate-access-matrix-doc.php`. No la edites a mano:';
$salida[] = '> el config es la fuente única de verdad y cualquier cambio manual';
$salida[] = '> se perderá en la siguiente regeneración.';
$salida[] = '';

$cabecera = '| Acción | ' . implode(' | ', array_values(ROLES)) . ' |';
$separador = '| --- | ' . implode(' | ', array_fill(0, count(ROLES), ':---:')) . ' |';

foreach (SECCIONES as $seccion => $abilities) {
    $salida[] = "### {$seccion}";
    $salida[] = '';
    $salida[] = $cabecera;
    $salida[] = $separador;

    foreach ($abilities as $ability) {
        if (!isset($matriz[$ability])) {
            continue;
        }
        $permitidos = $matriz[$ability];
        $celdas = [];
        foreach (array_keys(ROLES) as $rol) {
            $celdas[] = in_array($rol, $permitidos, true) ? 'Sí' : '—';
        }
        $etiqueta = ETIQUETAS[$ability] ?? $ability;
        $salida[] = "| {$etiqueta} | " . implode(' | ', $celdas) . ' |';
    }
    $salida[] = '';
}

$salida[] = '**Cómo se resuelven estos permisos.** `root` es global: pertenece a la';
$salida[] = 'plataforma, no a una empresa, y **no es un comodín** — solo puede lo que la';
$salida[] = 'tabla le concede explícitamente, por eso no aparece en firmar documentos ni';
$salida[] = 'en solicitar vacaciones. Los demás roles se resuelven **dentro de cada';
$salida[] = 'empresa**: una misma persona puede ser Admin Empleados en una y Empleado en';
$salida[] = 'otra, y el switcher de la barra superior decide con cuál está operando.';
$salida[] = '';
$salida[] = MARCA_FIN;

$tabla = implode("\n", $salida) . "\n";

// Modo --check: compara con el manual y falla si divergen.
if (in_array('--check', $argv ?? [], true)) {
    $manual = REPO_ROOT . 'docs/USER-MANUAL.md';
    $contenido = is_file($manual) ? file_get_contents($manual) : '';
    $ini = strpos($contenido, MARCA_INICIO);
    $fin = strpos($contenido, MARCA_FIN);

    if ($ini === false || $fin === false) {
        fwrite(STDERR, "ERROR: no encuentro las marcas MATRIZ-ACCESOS en docs/USER-MANUAL.md\n");
        exit(1);
    }

    $actual = substr($contenido, $ini, $fin - $ini + strlen(MARCA_FIN)) . "\n";
    if (trim($actual) !== trim($tabla)) {
        fwrite(STDERR, "ERROR: la matriz de docs/USER-MANUAL.md no coincide con el config.\n"
            . "Regenérala:  php scripts/generate-access-matrix-doc.php\n");
        exit(1);
    }

    exit(0);
}

echo $tabla;
