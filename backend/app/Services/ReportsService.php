<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reports Service
 *
 * Provides statistics and report data for the dashboard and exports.
 */
class ReportsService
{
    public function __construct(
        protected VacationBalanceService $vacationBalanceService
    ) {
    }

    /**
     * Get complete dashboard statistics.
     */
    public function getDashboardStats(?int $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        return [
            'documents' => $this->getDocumentStats($tenantId, $startDate, $endDate),
            'vacations' => $this->getVacationStats($tenantId, $startDate, $endDate),
            'users' => $this->getUserStats($tenantId),
            'recent_activity' => $this->getRecentActivity($tenantId, 10),
        ];
    }

    /**
     * Get document statistics.
     */
    public function getDocumentStats(?int $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        // Summary counts respect the selected date range when one is provided. When no range
        // is sent (default "Todo el tiempo"), they reflect the organization's all-time totals,
        // so the cards never show 0 just because the data is older than a default window.
        $query = Document::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = (clone $query)->count();
        $signed = (clone $query)->where('status', 'signed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $active = (clone $query)->where('status', 'active')->count();
        $orphan = (clone $query)->where('status', 'orphan')->count();

        // Documents by month for the trend chart — driven by the selected date range
        // (falls back to the last 6 months when no range is provided).
        $byMonth = $this->getDocumentsByMonth($tenantId, 6, $startDate, $endDate);

        // Documents by type (using document_types relationship) — respects the date range
        $byType = Document::query()
            ->join('document_types', 'documents.doc_type_id', '=', 'document_types.id')
            ->when($tenantId, fn($q) => $q->where('documents.tenant_id', $tenantId))
            ->when($startDate, fn($q) => $q->whereDate('documents.created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('documents.created_at', '<=', $endDate))
            ->select('document_types.name as type_name', DB::raw('count(*) as count'))
            ->groupBy('document_types.id', 'document_types.name')
            ->get()
            ->mapWithKeys(fn($item) => [$item->type_name ?? 'otros' => $item->count])
            ->toArray();

        // Status distribution for pie chart
        $statusDistribution = [
            ['name' => 'Firmados', 'value' => $signed, 'color' => '#10B981'],
            ['name' => 'Pendientes', 'value' => $pending, 'color' => '#F59E0B'],
            ['name' => 'Activos', 'value' => $active, 'color' => '#3B82F6'],
            ['name' => 'Huérfanos', 'value' => $orphan, 'color' => '#F97316'],
        ];

        return [
            'total' => $total,
            'signed' => $signed,
            'pending' => $pending,
            'active' => $active,
            'orphan' => $orphan,
            'by_month' => $byMonth,
            'by_type' => $byType,
            'status_distribution' => $statusDistribution,
        ];
    }

    /**
     * Get documents grouped by month.
     */
    private function getDocumentsByMonth(?int $tenantId, int $months = 6, ?string $startDate = null, ?string $endDate = null): array
    {
        $calcStartDate = $startDate
            ? Carbon::parse($startDate)->startOfMonth()
            : Carbon::now()->subMonths($months - 1)->startOfMonth();

        $calcEndDate = $endDate
            ? Carbon::parse($endDate)->endOfMonth()
            : Carbon::now()->endOfMonth();

        $query = Document::query()
            ->whereBetween('created_at', [$calcStartDate, $calcEndDate]);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // YEAR()/MONTH() son de MySQL; sqlite (usado en los tests) no las tiene.
        // Se emite el equivalente por driver: mismo resultado, mismo tipo.
        $isSqlite = $query->getConnection()->getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "cast(strftime('%Y', created_at) as integer)" : 'YEAR(created_at)';
        $monthExpr = $isSqlite ? "cast(strftime('%m', created_at) as integer)" : 'MONTH(created_at)';

        $results = $query
            ->select(
                DB::raw("{$yearExpr} as year"),
                DB::raw("{$monthExpr} as month"),
                DB::raw('count(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Fill in missing months with zero
        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $data = [];

        $current = $calcStartDate->copy();
        while ($current <= $calcEndDate) {
            $year = $current->year;
            $month = $current->month;

            $count = $results->first(fn($r) => $r->year == $year && $r->month == $month)?->count ?? 0;

            $data[] = [
                'name' => $monthNames[$month - 1],
                'value' => $count,
            ];
            $current->addMonth();
        }

        return $data;
    }

    /**
     * Get vacation statistics.
     * 
     * Note: Vacation stats always show the current year, regardless of date filters.
     * Date filters are intended for document stats, but vacations are measured by year.
     */
    public function getVacationStats(?int $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = VacationRequest::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Vacation stats are reported per current year by design (annual record),
        // independent of the dashboard's date-range filter. The response exposes
        // `current_year` so the UI can label the period explicitly.
        $currentYear = Carbon::now()->year;
        $query->whereYear('created_at', $currentYear);

        // El enum real de VacationRequest::status es
        // [pending, approved, rejected, cancelled] — 'pending_confirmation',
        // 'confirmed' y 'taken' nunca existieron como status; con ellos estos
        // conteos daban 0 siempre. "¿Tomó las vacaciones?" es un campo aparte
        // (was_taken, boolean), no un status.
        $total = (clone $query)->count();
        $pending = (clone $query)->where('status', VacationRequest::STATUS_PENDING)->count();
        $approved = (clone $query)->where('status', VacationRequest::STATUS_APPROVED)->count();
        $rejected = (clone $query)->where('status', VacationRequest::STATUS_REJECTED)->count();

        // Días efectivamente gozados: aprobadas Y confirmadas como tomadas
        // (was_taken=true), no solo aprobadas (que pueden seguir pendientes
        // de tomar).
        $totalDaysUsed = (clone $query)
            ->where('status', VacationRequest::STATUS_APPROVED)
            ->where('was_taken', true)
            ->sum('days_requested');

        // Requests by status for pie chart
        $statusDistribution = [
            ['name' => 'Aprobadas', 'value' => $approved, 'color' => '#10B981'],
            ['name' => 'Pendientes', 'value' => $pending, 'color' => '#F59E0B'],
            ['name' => 'Rechazadas', 'value' => $rejected, 'color' => '#EF4444'],
        ];

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total_days_used' => $totalDaysUsed,
            'status_distribution' => $statusDistribution,
            'current_year' => $currentYear,
        ];
    }

    /**
     * Get user statistics.
     */
    public function getUserStats(?int $tenantId = null): array
    {
        if ($tenantId) {
            // Get users for specific tenant
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return $this->getEmptyUserStats();
            }

            // Empleados = User::ORG_EMPLOYEE_ROLES (admin/client/aprobador) EN
            // esta empresa (ver Tenant::employeesQuery()) — quedan fuera las
            // cuentas de aplicación: root (global, nunca tiene fila aquí) y
            // admin_tenant. Un builder NUEVO por métrica: al reutilizar uno
            // solo, los where se acumulaban sobre la misma instancia y
            // producían `status = 'active' AND status IN (...)`, insatisfacible
            // — 'inactive' devolvía siempre 0. ->where('users.status', ...)
            // calificado y ->count('users.id') porque employeesQuery() ya
            // trae joins + distinct('users.id').
            $total = $tenant->employeesQuery()->count('users.id');
            $active = $tenant->employeesQuery()->where('users.status', 'active')->count('users.id');
            $inactive = $tenant->employeesQuery()
                ->whereIn('users.status', ['inactive', 'terminated', 'pending'])
                ->count('users.id');

            // Roles DENTRO de esta empresa: user_tenant_roles, acotado a
            // ORG_EMPLOYEE_ROLES (admin_tenant no es un "rol de empleado" — ver
            // arriba). Antes se unía contra user_roles (el respaldo global, que
            // es la UNIÓN de los roles del usuario en TODAS sus empresas), así
            // que a un usuario multi-empresa se le contaba aquí el rol que
            // tiene en otra. COUNT(DISTINCT ...) por si el usuario tuviera más
            // de una fila para el mismo rol en esta empresa. Se excluye a
            // quien sea admin_tenant EN esta empresa (regla "admin_tenant
            // domina", ver Tenant::employeesQuery()).
            $byRole = DB::table('user_tenant_roles')
                ->join('users', 'user_tenant_roles.user_id', '=', 'users.id')
                ->join('roles', 'user_tenant_roles.role_id', '=', 'roles.id')
                ->where('user_tenant_roles.tenant_id', $tenantId)
                ->whereIn('roles.name', User::ORG_EMPLOYEE_ROLES)
                ->whereNull('users.deleted_at')
                ->whereNotExists($this->adminTenantInSameTenantSubquery())
                ->select('roles.name', DB::raw('count(distinct user_tenant_roles.user_id) as count'))
                ->groupBy('roles.name')
                ->get()
                ->mapWithKeys(fn($item) => [$item->name => $item->count])
                ->toArray();
        } else {
            // Global stats: empleados = tiene AL MENOS UN rol de
            // User::ORG_EMPLOYEE_ROLES en una empresa donde NO es admin_tenant
            // (regla "admin_tenant domina" por empresa) — root y admin_tenant
            // quedan fuera. Antes se contaba User::count() a secas (incluía
            // cuentas de aplicación) y by_role se unía contra el respaldo
            // global user_roles (donde solo vive 'root'), así que ningún rol
            // operativo por empresa aparecía nunca aquí.
            $employees = fn() => User::whereHas('tenantRoles', function ($q) {
                $q->whereHas('role', fn($r) => $r->whereIn('name', User::ORG_EMPLOYEE_ROLES))
                  ->whereNotExists($this->adminTenantInSameTenantSubquery());
            });

            $total = $employees()->count();
            $active = $employees()->where('status', 'active')->count();
            $inactive = $employees()->whereIn('status', ['inactive', 'terminated', 'pending'])->count();

            // by_role directo sobre user_tenant_roles: COUNT(DISTINCT user_id)
            // por rol para que un usuario con el mismo rol en varias empresas
            // (p. ej. 'client' en A y B) cuente 1 sola vez bajo ese rol. Las
            // filas de empresas donde el usuario es admin_tenant no cuentan.
            $byRole = DB::table('user_tenant_roles')
                ->join('users', 'user_tenant_roles.user_id', '=', 'users.id')
                ->join('roles', 'user_tenant_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.name', User::ORG_EMPLOYEE_ROLES)
                ->whereNull('users.deleted_at')
                ->whereNotExists($this->adminTenantInSameTenantSubquery())
                ->select('roles.name', DB::raw('count(distinct user_tenant_roles.user_id) as count'))
                ->groupBy('roles.name')
                ->get()
                ->mapWithKeys(fn($item) => [$item->name => $item->count])
                ->toArray();
        }

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'by_role' => $byRole,
        ];
    }

    private function getEmptyUserStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'by_role' => [],
        ];
    }

    /**
     * Get recent activity from audit logs.
     */
    public function getRecentActivity(?int $tenantId = null, int $limit = 10): array
    {
        $query = AuditLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'Sistema',
                'action' => $log->action,
                'description' => $log->description,
                'category' => $log->category,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'created_at' => $log->created_at->toIso8601String(),
                'time_ago' => $log->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Get audit logs with pagination and filters.
     */
    public function getAuditLogs(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::with(['user:id,name,email', 'tenant:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->ofAction($filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $query->ofEntity($filters['entity_type']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            // Nombre completo: ver User::scopeMatchingFullName — antes solo
            // comparaba `name` contra el término completo, así que "Juan
            // Pérez" nunca encontraba al autor del log (name=Juan,
            // last_name=Pérez).
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($u) => $u->matchingFullName($search));
            });
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get document report data for export.
     */
    public function getDocumentReportData(array $filters = []): \Illuminate\Support\Collection
    {
        $query = Document::with(['user:id,name,email', 'tenant:id,name', 'documentType:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['doc_type_id'])) {
            $query->where('doc_type_id', $filters['doc_type_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->get()->map(function ($doc) {
            return [
                'id' => $doc->id,
                'nombre' => $doc->original_name ?? 'N/A',
                'tipo' => $doc->documentType?->name ?? 'N/A',
                'usuario' => $doc->user?->name ?? 'N/A',
                'email' => $doc->user?->email ?? 'N/A',
                'estado' => $this->translateStatus($doc->status),
                'fecha_creacion' => $doc->created_at?->format('Y-m-d H:i'),
                'fecha_firma' => $doc->signed_at?->format('Y-m-d H:i') ?? 'N/A',
                'organizacion' => $doc->tenant?->name ?? 'N/A',
            ];
        });
    }

    /**
     * Get vacation report data for export.
     */
    public function getVacationReportData(array $filters = []): \Illuminate\Support\Collection
    {
        $query = VacationRequest::with(['user:id,name,email', 'approvedByUser:id,name', 'tenant:id,name'])
            ->orderBy('start_date', 'desc');

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Date range filters (by created_at)
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Search filter (by user full name or email). Nombre completo vía
        // User::scopeMatchingFullName — antes 'name' y 'last_name' se
        // comparaban por separado contra el término completo, así que
        // "Juan Pérez" nunca encontraba a name=Juan, last_name=Pérez.
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->matchingFullName($search)
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->get()->map(function ($vacation) {
            $startDate = $vacation->start_date ? Carbon::parse($vacation->start_date)->format('Y-m-d') : 'N/A';
            $endDate = $vacation->end_date ? Carbon::parse($vacation->end_date)->format('Y-m-d') : 'N/A';
            $approvedAt = $vacation->approved_at ? Carbon::parse($vacation->approved_at)->format('Y-m-d H:i') : 'N/A';

            return [
                'id' => $vacation->id,
                'empleado' => $vacation->user?->name ?? 'N/A',
                'email' => $vacation->user?->email ?? 'N/A',
                'fecha_inicio' => $startDate,
                'fecha_fin' => $endDate,
                'dias' => $vacation->days_requested,
                'estado' => $this->translateVacationStatus($vacation->status),
                'aprobador' => $vacation->approvedByUser?->name ?? 'N/A',
                'fecha_aprobacion' => $approvedAt,
                'motivo_rechazo' => $vacation->rejection_reason ?? 'N/A',
                'organizacion' => $vacation->tenant?->name ?? 'N/A',
            ];
        });
    }

    /**
     * Get users report data for export.
     *
     * Empleados = roles de empresa User::ORG_EMPLOYEE_ROLES (admin/client/
     * aprobador) POR ROL EN CADA EMPRESA — nunca cuentas de aplicación (root,
     * global; admin_tenant, administrador de empresa). Ver
     * Tenant::employeesQuery() y User::ORG_EMPLOYEE_ROLES.
     */
    public function getUsersReportData(array $filters = []): \Illuminate\Support\Collection
    {
        $query = User::with(['tenants:id,name', 'tenantRoles.role'])
            ->orderBy('name');

        $tenantId = $filters['tenant_id'] ?? null;
        $hasSpecificTenant = is_numeric($tenantId) && (int) $tenantId > 0;

        if ($hasSpecificTenant) {
            $tenantId = (int) $tenantId;

            // Empresa concreta: miembro de la empresa Y con un rol de empleado
            // EN ella, Y sin rol admin_tenant EN ella (regla "admin_tenant
            // domina": quien es admin_tenant de esta empresa es cuenta de
            // aplicación aquí, aunque además tenga un rol de empleado — ver
            // Tenant::employeesQuery()).
            $query->whereHas('tenants', fn($q) => $q->where('tenants.id', $tenantId));
            $query->whereHas('tenantRoles', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->whereHas('role', fn($r) => $r->whereIn('name', User::ORG_EMPLOYEE_ROLES));
            });
            $query->whereDoesntHave('tenantRoles', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->whereHas('role', fn($r) => $r->where('name', 'admin_tenant'));
            });
        } else {
            // Root global ("todas las empresas"): whitelist positiva — tiene
            // un rol de empleado en ALGUNA empresa donde NO es admin_tenant
            // (regla "admin_tenant domina" por empresa: el dual admin_tenant
            // en A / client en B sigue apareciendo por B; el dual
            // client+admin_tenant SOLO en A no es empleado en ninguna parte)
            // — + cinturón de seguridad (no es root).
            $query->whereHas('tenantRoles', function ($q) {
                $q->whereHas('role', fn($r) => $r->whereIn('name', User::ORG_EMPLOYEE_ROLES))
                    ->whereNotExists($this->adminTenantInSameTenantSubquery());
            });
            $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'root'));
        }

        // Actor que exporta (ReportsController::exportUsers): replica la
        // exclusión de UserController::index — un no-root nunca se ve a sí
        // mismo ni a ningún admin_tenant (solo root administra esas cuentas).
        // Root no tiene esta exclusión.
        $actor = $filters['acting_user'] ?? null;
        if ($actor instanceof User && !$actor->isRoot()) {
            $query->where('users.id', '!=', $actor->id)
                ->whereDoesntHave('tenantRoles', function ($q) {
                    $q->whereHas('role', fn($r) => $r->where('name', 'admin_tenant'));
                });
        }

        // Nombre completo vía User::scopeMatchingFullName — antes solo
        // comparaba `name` contra el término completo (ni siquiera
        // consideraba `last_name`), así que "Juan Pérez" nunca encontraba
        // a name=Juan, last_name=Pérez.
        //
        // De paso: la columna era 'document_id', que no existe en `users`
        // (el DNI es `document_text` — ver UserController::index, que sí lo
        // usa bien). En MySQL eso rompe la query con "Unknown column" en
        // cuanto se manda cualquier término de búsqueda.
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->matchingFullName($search)
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document_text', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $users = $query->get();

        // B3: saldo de vacaciones (Pendientes/Gozadas/Truncas/Saldo) en lote
        // — VacationBalanceService::getBalancesForUsers hace 2 queries
        // totales sin importar N, nunca un getBalance() por usuario dentro
        // de este map(). Solo se calcula cuando el export está acotado a UNA
        // empresa concreta (mismo $filters['tenant_id'] que ya filtra la
        // query de arriba, resuelto en ReportsController::getTenantId). Un
        // export "todas las empresas" (root sin empresa activa, tenant_id
        // null) puede mezclar usuarios de distintas empresas, cada uno con
        // su propio hire_date/saldo — no hay una única cifra correcta por
        // fila, así que se deja "N/A" en vez de inventar una suma entre
        // empresas (ver SPEC-VACACIONES v2).
        $tenantIdForBalance = $filters['tenant_id'] ?? null;
        $vacationBalances = (is_int($tenantIdForBalance) && $tenantIdForBalance > 0)
            ? $this->vacationBalanceService->getBalancesForUsers($users, $tenantIdForBalance)
            : [];

        return $users->map(function ($user) use ($vacationBalances, $hasSpecificTenant, $tenantId) {
            // Rol de EMPLEADO de mayor prioridad: en la empresa exportada
            // (tenant_id concreto), o entre todas sus empresas (export
            // global) — nunca vía getCurrentRole() sin tenant, que podía
            // devolver el rol de OTRA empresa distinta a la exportada.
            // Los roles de empresas donde el usuario es admin_tenant no
            // cuentan (regla "admin_tenant domina": ahí es cuenta de
            // aplicación, no empleado).
            $adminTenantTenantIds = $user->tenantRoles
                ->filter(fn($tr) => $tr->role?->name === 'admin_tenant')
                ->pluck('tenant_id')
                ->all();
            $employeeTenantRoles = $user->tenantRoles
                ->reject(fn($tr) => in_array($tr->tenant_id, $adminTenantTenantIds, true));
            $tenantRoleNames = $hasSpecificTenant
                ? $employeeTenantRoles->where('tenant_id', $tenantId)->pluck('role.name')
                : $employeeTenantRoles->pluck('role.name');
            $role = User::highestPriorityRole(
                $tenantRoleNames->filter()->intersect(User::ORG_EMPLOYEE_ROLES)
            );
            $tenantNames = $user->tenants->pluck('name')->join(', ');
            $balance = $vacationBalances[$user->id] ?? null;

            // Las claves de este array SON los encabezados del Excel:
            // GenericExport usa array_keys() de la primera fila cuando no se
            // pasan headings explícitos. Por eso van en Title Case legible y
            // no en snake_case — este archivo lo abre el cliente, no un
            // proceso. Las 4 de vacaciones usan su vocabulario (mensaje del
            // 31/07/2026), normalizado al mismo estilo que el resto.
            return [
                'ID' => $user->id,
                'Nombre' => $user->name,
                'Email' => $user->email,
                // Display name (Obs 2), no el slug crudo: root no está en el
                // mapa de BulkUserUploadService::orgRoleLabel (no es un rol
                // por organización) y se devuelve tal cual, sin romper el export.
                'Rol' => $role ? BulkUserUploadService::orgRoleLabel($role) : 'N/A',
                'Activo' => $user->status === 'active' ? 'Sí' : 'No',
                'Estado' => $user->status ?? 'N/A',
                'Organizaciones' => $tenantNames ?: 'N/A',
                'Fecha de registro' => $user->created_at?->format('Y-m-d'),
                'Último acceso' => $user->last_login_at?->format('Y-m-d H:i') ?? 'N/A',
                'Vacaciones Pendientes' => $balance['pending'] ?? 'N/A',
                'Vacaciones Gozadas' => $balance['taken'] ?? 'N/A',
                'Vacaciones Truncas' => $balance['truncated'] ?? 'N/A',
                'Saldo Vacaciones' => $balance['balance'] ?? 'N/A',
            ];
        });
    }

    /**
     * Get app accounts (cuentas de aplicación) report data for export.
     *
     * A diferencia de getUsersReportData() (empleados: admin/client/aprobador
     * por empresa), este export cubre las cuentas de PLATAFORMA: root
     * (global, siempre incluido) y admin_tenant (administrador de empresa).
     *
     * @param  ?array<int>  $tenantIds  null = sin restricción (root ve todo).
     *   Para un viewer no-root, la empresa ACTIVA en la que está operando
     *   (ver ReportsController::exportAppAccounts) — acota tanto QUÉ
     *   admin_tenant se listan como QUÉ empresas se muestran en su columna.
     * @param  bool  $includeRoots  false para viewers no-root: las cuentas
     *   root son de la plataforma, no de la empresa activa.
     */
    public function getAppAccountsReportData(?array $tenantIds = null, bool $includeRoots = true): \Illuminate\Support\Collection
    {
        $eagerLoads = ['tenants:id,name', 'tenantRoles.role', 'tenantRoles.tenant:id,name'];

        // (a) Roots de plataforma: solo para viewers root ($includeRoots).
        $roots = $includeRoots
            ? User::whereHas('roles', fn($q) => $q->where('name', 'root'))
                ->with($eagerLoads)
                ->get()
            : collect();
        $rootIds = $roots->pluck('id')->flip();

        // (b) admin_tenant, opcionalmente acotado a $tenantIds (empresas que
        // administra el viewer no-root).
        $adminTenants = User::whereHas('tenantRoles', function ($q) use ($tenantIds) {
            $q->whereHas('role', fn($r) => $r->where('name', 'admin_tenant'));
            if ($tenantIds !== null) {
                $q->whereIn('tenant_id', $tenantIds);
            }
        })->with($eagerLoads)->get();

        // (c) merge: si un usuario es root Y admin_tenant, gana root — unique()
        // conserva la PRIMERA ocurrencia de cada id, y $roots va primero.
        $users = $roots->concat($adminTenants)->unique('id')->sortBy('name')->values();

        return $users->map(function ($user) use ($rootIds, $tenantIds) {
            $isRoot = $rootIds->has($user->id);

            if ($isRoot) {
                $tipo = 'Administrador Plataforma';
                $empresas = 'Todas (plataforma)';
            } else {
                $tipo = BulkUserUploadService::orgRoleLabel('admin_tenant');

                // Empresas donde administra como admin_tenant, restringidas a
                // $tenantIds cuando el viewer NO es root: nunca se filtran
                // nombres de empresas ajenas al viewer.
                $adminTenantRoles = $user->tenantRoles->filter(
                    fn($tr) => $tr->role?->name === 'admin_tenant'
                );
                if ($tenantIds !== null) {
                    $adminTenantRoles = $adminTenantRoles->filter(
                        fn($tr) => in_array($tr->tenant_id, $tenantIds, true)
                    );
                }

                $empresas = $adminTenantRoles->pluck('tenant.name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->join(', ');
                $empresas = $empresas !== '' ? $empresas : 'N/A';
            }

            return [
                'ID' => $user->id,
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Tipo de cuenta' => $tipo,
                'Empresas que administra' => $empresas,
                'Estado' => $user->status === 'active' ? 'Activo' : 'Inactivo',
                'Fecha de registro' => $user->created_at?->format('Y-m-d'),
                'Último acceso' => $user->last_login_at?->format('Y-m-d H:i') ?? 'N/A',
            ];
        });
    }

    /**
     * Subconsulta correlacionada para whereNotExists en queries cuya tabla
     * activa es user_tenant_roles (whereHas('tenantRoles') o DB::table):
     * excluye las filas de empresas donde ESE usuario tiene además el rol
     * admin_tenant. Regla "admin_tenant domina" por empresa — quien es
     * admin_tenant de una empresa es cuenta de aplicación ahí, aunque tenga
     * también un rol de empleado (ver Tenant::employeesQuery()).
     */
    private function adminTenantInSameTenantSubquery(): \Closure
    {
        return function ($sub) {
            $sub->select(DB::raw(1))
                ->from('user_tenant_roles as utr_admin')
                ->join('roles as r_admin', 'r_admin.id', '=', 'utr_admin.role_id')
                ->whereColumn('utr_admin.user_id', 'user_tenant_roles.user_id')
                ->whereColumn('utr_admin.tenant_id', 'user_tenant_roles.tenant_id')
                ->where('r_admin.name', 'admin_tenant');
        };
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'active' => 'Activo',
            'signed' => 'Firmado',
            'expired' => 'Vencido',
            'orphan' => 'Huérfano',
            default => ucfirst($status),
        };
    }

    private function translateVacationStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'pending_confirmation' => 'Por confirmar',
            'confirmed' => 'Confirmada',
            'taken' => 'Tomada',
            'not_taken' => 'No tomada',
            default => $status,
        };
    }

    /**
     * Get batch data for export.
     */
    public function getBatchReportData(array $filters = []): \Illuminate\Support\Collection
    {
        $query = \App\Models\DocumentBatch::with(['tenant', 'uploadedBy', 'documentType'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->get()->map(function ($batch) {
            return [
                'id' => $batch->id,
                'organizacion' => $batch->tenant->name ?? 'N/A',
                'tipo_documento' => $batch->documentType->display_name ?? 'N/A',
                'periodo' => $batch->period,
                'archivo_original' => $batch->original_filename,
                'total_archivos' => $batch->total_files,
                'procesados' => $batch->processed_files,
                'exitosos' => $batch->success_count,
                'reemplazados' => $batch->replaced_count,
                'huerfanos' => $batch->orphan_count,
                'errores' => $batch->error_count,
                'estado' => $this->translateBatchStatus($batch->status),
                'requiere_firma' => $batch->requires_signature ? 'Sí' : 'No',
                'subido_por' => $batch->uploadedBy->name ?? 'N/A',
                'fecha_creacion' => $batch->created_at?->format('Y-m-d H:i'),
                'fecha_completado' => $batch->completed_at?->format('Y-m-d H:i') ?? 'N/A',
            ];
        });
    }

    private function translateBatchStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'completed_with_errors' => 'Completado con errores',
            'failed' => 'Fallido',
            default => $status,
        };
    }

    /**
     * Get tenant data for export.
     */
    public function getTenantReportData(array $filters = []): \Illuminate\Support\Collection
    {
        // 'users' fuera del withCount: contaba TODOS los miembros del tenant
        // (incluye admin_tenant, que no es un "usuario/empleado" de la
        // empresa). La columna 'usuarios' se recalcula por fila con
        // Tenant::employeesQuery() (whitelist de User::ORG_EMPLOYEE_ROLES);
        // N+1 aceptable en un export.
        $query = Tenant::withCount(['documents'])
            ->orderBy('name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'nombre' => $tenant->name,
                'ruc' => $tenant->ruc ?? 'N/A',
                'email' => $tenant->email ?? 'N/A',
                'telefono' => $tenant->phone ?? 'N/A',
                'direccion' => $tenant->address ?? 'N/A',
                'empleados' => $tenant->employeesQuery()->count('users.id'),
                // Export solo-root (ReportsController::exportTenants):
                // admin_tenant de la empresa, complemento de 'empleados'
                // bajo la regla "admin_tenant domina" (no se solapan).
                'cuentas_aplicacion' => $tenant->appAccountsQuery()->count('users.id'),
                'documentos' => $tenant->documents_count,
                'estado' => $tenant->status === 'active' ? 'Activo' : 'Inactivo',
                'fecha_creacion' => $tenant->created_at?->format('Y-m-d'),
            ];
        });
    }
}
