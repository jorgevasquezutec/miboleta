<?php

/*
|--------------------------------------------------------------------------
| Matriz de Accesos — fuente única de verdad de autorización
|--------------------------------------------------------------------------
|
| Formato: ability => [roles permitidos].
|
| Transcrita literal de docs/sprintfix/Matriz de Accesos.xlsx. Las entradas
| marcadas [MATRIZ] salen tal cual del Excel y NO se "corrigen" aunque luzcan
| inconsistentes (p. ej. 'dashboard.global_metrics' no incluye 'admin', y el
| 'aprobador' no puede ver sus propios documentos). Las marcadas [INFERIDA] no
| están en el Excel: reflejan el comportamiento actual del código y requieren
| confirmación de producto.
|
| Reglas de resolución (ver App\Models\User::roleForTenant / hasAbility):
|
| - 'root' es GLOBAL (plataforma). NO es un comodín: se lista explícitamente
|   solo en las abilities donde la matriz se lo concede. Por eso NO existe un
|   Gate::before que le dé acceso a todo — la matriz lo excluye de varias
|   acciones operativas (firmar, solicitar vacaciones, cargar ZIP...).
| - Los roles operativos (admin, admin_tenant, aprobador, client) se resuelven
|   SIEMPRE dentro de una empresa concreta (user_tenant_roles), nunca contra el
|   respaldo global `user_roles` (que es la UNIÓN de roles de todas las empresas
|   del usuario y produce fugas de privilegios entre empresas).
| - "SI (*)" del Excel ("solo en las organizaciones que gestiona") no necesita
|   representarse aquí: al resolverse el rol dentro del tenant, un admin_tenant
|   simplemente no tiene rol en las empresas que no gestiona.
|
| Al agregar una ability nueva aquí queda automáticamente registrada como Gate
| (ver AppServiceProvider::boot) y expuesta al frontend (GET /api/access-matrix).
|
*/

return [

    // ── GESTIÓN DE EMPRESAS ── [MATRIZ]
    // Crear / editar / desactivar / ver lista de organizaciones.
    'tenants.manage' => ['root'],
    // [OBS-CLIENTE 2026-08] Obs 3: acceso de SOLO LECTURA al módulo Empresas
    // para 'admin_tenant' (Admin Clientes) — solo las organizaciones que
    // administra (el scoping de datos ya vive en TenantService::getTenants
    // y ::canAccessTenant, no aquí). Sin crear/editar/eliminar: eso sigue
    // siendo 'tenants.manage' (solo root).
    'tenants.view' => ['root', 'admin_tenant'],

    // ── GESTIÓN DE USUARIOS ── [MATRIZ]
    'users.create_any_role' => ['root'],
    // Crear usuario con rol aprobador/usuario. OJO: esta ability solo controla
    // si el endpoint es alcanzable; la regla fina de QUÉ roles puede asignar un
    // admin_tenant es validación de payload y vive en StoreUserRequest.
    'users.create_limited_role' => ['root', 'admin_tenant'],
    'users.view_list' => ['root', 'admin', 'admin_tenant'],
    // [OBS-CLIENTE 2026-07] C3: 'admin' (Admin Empleados) ahora puede editar
    // usuarios — antes solo root/admin_tenant. QUÉ puede editar (solo puede
    // reasignar los roles aprobador/client) es validación de payload y vive
    // en UserService::assignableRoleNamesFor(); QUÉ usuarios puede alcanzar
    // (no a otro admin, no a un admin_tenant, no a sí mismo) lo resuelve
    // UserService::canManageUser() en el controller.
    'users.update' => ['root', 'admin', 'admin_tenant'],
    'users.deactivate' => ['root', 'admin_tenant'],
    'users.delete' => ['root'],
    // [OBS-CLIENTE 2026-08] Habilitar (restaurar) una cuenta eliminada. Solo
    // root, igual que 'users.delete': es la operación inversa y el cliente la
    // pidió explícitamente acotada a root. Gatea tanto el listado de
    // eliminados (GET /users?deleted=1) como POST /users/{id}/restore.
    // OJO: no confundir con 'users.deactivate', que es el eje status
    // (active/inactive) y no toca deleted_at.
    'users.restore' => ['root'],
    'users.reset_password' => ['root', 'admin_tenant'],
    'users.bulk_upload' => ['root', 'admin_tenant'],
    // [OBS-CLIENTE 2026-08] Export de empleados (ReportsController::exportUsers).
    // Mismo conjunto efectivo que el gate anterior (authorizeReports =
    // dashboard.global_metrics ∪ org_metrics: root, admin, admin_tenant) — cero
    // cambio de acceso, solo un nombre propio para useCan en el frontend.
    'users.export' => ['root', 'admin', 'admin_tenant'],

    // ── GESTIÓN DE DOCUMENTOS ── [MATRIZ]
    'documents.view_all_multi_tenant' => ['root', 'admin_tenant'],
    'documents.view_org' => ['root', 'admin', 'admin_tenant'],
    'documents.view_own' => ['admin', 'client', 'admin_tenant'],
    'documents.bulk_upload_zip' => ['admin', 'admin_tenant'],
    'documents.view_batches' => ['admin', 'admin_tenant'],
    'documents.export' => ['root', 'admin', 'admin_tenant'],
    'documents.download_own' => ['admin', 'client', 'admin_tenant'],
    'documents.sign_2fa' => ['admin', 'client'],

    // ── GESTIÓN DE VACACIONES ── [MATRIZ]
    'vacations.request_own' => ['admin', 'client', 'admin_tenant'],
    'vacations.view_own_requests' => ['admin', 'client', 'admin_tenant'],
    'vacations.cancel_own_pending' => ['admin', 'client', 'admin_tenant', 'aprobador'],
    'vacations.approve_reject_team' => ['admin', 'admin_tenant', 'aprobador'],
    'vacations.confirm_preapproved' => ['admin', 'admin_tenant', 'aprobador'],
    'vacations.view_team_calendar' => ['admin', 'admin_tenant'],
    'vacations.view_history' => ['root', 'admin', 'client', 'admin_tenant'],

    // ── AUDITORÍA Y REPORTES ── [MATRIZ]
    'audit.view' => ['root', 'admin', 'admin_tenant'],
    'audit.export' => ['root', 'admin', 'admin_tenant'],
    // [OBS-CLIENTE 2026-08] Export de cuentas de aplicación (root + admin_tenant).
    // Solo root por decisión del cliente (2026-08): admin_tenant no ve estas
    // cuentas en la tabla de usuarios, así que tampoco las descarga. Si algún
    // día se le da visibilidad en UI, reabrir aquí y en exportAppAccounts
    // (el service ya soporta scoping por empresa e includeRoots).
    'reports.app_accounts_export' => ['root'],

    // ── DASHBOARD ── [MATRIZ]
    'dashboard.global_metrics' => ['root', 'admin_tenant'],
    'dashboard.org_metrics' => ['admin', 'admin_tenant'],
    'dashboard.own_summary' => ['admin', 'client', 'admin_tenant'],

    /*
    |--------------------------------------------------------------------------
    | [INFERIDA] — acciones que el Excel NO cubre
    |--------------------------------------------------------------------------
    | La Matriz de Accesos no dice nada sobre estas acciones. Por eso aquí se
    | PRESERVA EXACTAMENTE el comportamiento actual del código: donde la matriz
    | calla, no se cambia quién puede qué. Cualquier ajuste requiere una decisión
    | de producto explícita (y sería un cambio aparte, no parte del cierre de la
    | fuga de privilegios).
    */

    // Hoy: DocumentService::deleteDocument permite a cualquiera que no sea
    // 'client' (incluye 'aprobador'). Se preserva tal cual.
    'documents.delete' => ['root', 'admin', 'admin_tenant', 'aprobador'],
    // Hoy: DocumentController::orphans() bloquea solo a 'client'. Se preserva.
    'documents.view_orphans' => ['root', 'admin', 'admin_tenant', 'aprobador'],
    // Hoy: DocumentController::signDigital() = ['root','admin'].
    // Firma PAdES con el certificado de plataforma: distinta de
    // 'documents.sign_2fa' (firma del empleado sobre su propio documento).
    'documents.sign_digital' => ['root', 'admin'],
    // Hoy: AssignOrphanRequest::authorize() = ['root','admin'].
    'documents.assign_orphan' => ['root', 'admin'],
    // Hoy: AssignTenantsRequest::authorize() = ['root','admin'] (TenantController::addUser,
    // asignar empresas a un usuario). La matriz no dice si la membresía es
    // "gestión de usuarios" (users.update) o "gestión de empresas"
    // (tenants.manage); se preserva el comportamiento actual hasta que producto
    // decida.
    'tenants.assign_users' => ['root', 'admin'],
    // Hoy: UpdateTenantSettingsRequest::authorize() = ['root','admin'].
    'tenants.update_settings' => ['root', 'admin'],
    // Hoy: isRoot() en PlatformSettingsService / SignatureCertificateService /
    // AuditSettingsService. Ajustes de plataforma.
    'platform.manage' => ['root'],

];
