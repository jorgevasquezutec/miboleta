<?php

namespace App\Http\Controllers\Api;

use App\Exports\GenericExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ActiveTenantResolver;
use App\Services\DocumentService;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function __construct(
        private ReportsService $reportsService,
        private DocumentService $documentService,
        private ActiveTenantResolver $activeTenantResolver
    ) {
    }

    /**
     * Get dashboard statistics.
     * 
     * GET /api/reports/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $stats = $this->reportsService->getDashboardStats($tenantId, $startDate, $endDate);

        return response()->json([
            'data' => $stats,
            'tenant_id' => $tenantId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Get the authenticated user's own document statistics (individual dashboard).
     * Returns real totals (total / signed / pending) across all the user's documents,
     * independent of pagination or list filters.
     *
     * GET /api/reports/my-stats
     */
    public function myStats(Request $request): JsonResponse
    {
        // Same scope as the documents list (date range, type, search) EXCEPT status,
        // so the summary cards stay consistent with the filtered list.
        $filters = [
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'search' => $request->search,
            'doc_type_id' => $request->doc_type_id,
            'period' => $request->period,
        ];

        $stats = $this->documentService->getMyDocumentStats($request->user(), $filters);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get document statistics.
     *
     * GET /api/reports/documents
     */
    public function documents(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $stats = $this->reportsService->getDocumentStats($tenantId);

        return response()->json([
            'data' => $stats,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get vacation statistics.
     * 
     * GET /api/reports/vacations
     */
    public function vacations(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $stats = $this->reportsService->getVacationStats($tenantId);

        return response()->json([
            'data' => $stats,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get user statistics.
     * 
     * GET /api/reports/users
     */
    public function users(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $stats = $this->reportsService->getUserStats($tenantId);

        return response()->json([
            'data' => $stats,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get recent activity.
     * 
     * GET /api/reports/activity
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);
        $limit = min($request->query('limit', 20), 100);

        $activity = $this->reportsService->getRecentActivity($tenantId, $limit);

        return response()->json([
            'data' => $activity,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get audit logs with pagination.
     * 
     * GET /api/reports/audit
     */
    public function audit(Request $request): JsonResponse
    {
        $user = $request->user();
        [$tenantId, $role] = $this->resolveAuditContext($request, $user);

        if (!in_array($role, self::AUDIT_ROLES, true)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $filters = [
            'tenant_id' => $tenantId,
            'user_id' => $request->query('user_id'),
            'action' => $request->query('action'),
            'entity_type' => $request->query('entity_type'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'search' => $request->query('search'),
            'per_page' => min($request->query('per_page', 20), 100),
        ];

        $logs = $this->reportsService->getAuditLogs($filters);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get available audit actions for filtering.
     * 
     * GET /api/reports/audit/actions
     */
    public function auditActions(): JsonResponse
    {
        // Fuente única de verdad: se construye desde el catálogo de acciones
        // (AuditLog::allActions() + descripción), agrupado por categoría, para
        // no mantener listas paralelas desincronizadas.
        $actions = [];
        foreach (AuditLog::allActions() as $action) {
            $category = explode('.', $action)[0] ?? 'other';
            $label = (new AuditLog(['action' => $action]))->description;
            $actions[$category][$action] = $label;
        }

        return response()->json(['data' => $actions]);
    }

    /**
     * Export documents to Excel.
     * 
     * GET /api/reports/documents/export
     */
    public function exportDocuments(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $filters = [
            'tenant_id' => $tenantId,
            'status' => $request->query('status'),
            'document_type' => $request->query('document_type'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];

        $data = $this->reportsService->getDocumentReportData($filters);

        return $this->exportToExcel($data->toArray(), 'documentos');
    }

    /**
     * Export vacations to Excel.
     * 
     * GET /api/reports/vacations/export
     */
    public function exportVacations(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $filters = [
            'tenant_id' => $tenantId,
            'status' => $request->query('status'),
            'year' => $request->query('year'),
            'user_id' => $request->query('user_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        $data = $this->reportsService->getVacationReportData($filters);

        return $this->exportToExcel($data->toArray(), 'vacaciones');
    }

    /**
     * Export users to Excel.
     *
     * GET /api/reports/users/export
     *
     * [OBS-CLIENTE 2026-08] Ability propia 'users.export' en vez de
     * authorizeReports(): mismo conjunto efectivo de roles (root, admin,
     * admin_tenant), pero con nombre propio expuesto a /api/access-matrix
     * para que el frontend pueda gatear el botón de export con useCan().
     */
    public function exportUsers(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        abort_unless($user->hasAbility('users.export', $tenantId), 403);

        $filters = [
            'tenant_id' => $tenantId,
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'is_active' => $request->query('is_active'),
            // Actor que exporta: ReportsService replica la exclusión de
            // UserController::index (no verse a sí mismo, ni a admin_tenant)
            // para todo actor no-root.
            'acting_user' => $user,
        ];

        $data = $this->reportsService->getUsersReportData($filters);

        return $this->exportToExcel($data->toArray(), 'usuarios');
    }

    /**
     * Export de cuentas de aplicación (root + admin_tenant) a Excel.
     *
     * GET /api/reports/app-accounts/export
     *
     * A diferencia de exportUsers() (empleados: admin/client/aprobador por
     * empresa), este export cubre las cuentas de PLATAFORMA: root (global) y
     * admin_tenant (administradores de empresa). Scope: root ve todas las
     * cuentas; un admin_tenant ve los roots de la plataforma + los
     * admin_tenant de las empresas que ÉL administra (no todas sus
     * membresías — ver getAppAccountsReportData).
     */
    public function exportAppAccounts(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        abort_unless($user->hasAbility('reports.app_accounts_export', $tenantId), 403);

        // La ability es solo-root por ahora: exporta todas las cuentas de
        // aplicación (roots + admin_tenant de todas las empresas). El service
        // soporta scoping por empresa e includeRoots por si se reabre a
        // admin_tenant con visibilidad en UI.
        $data = $this->reportsService->getAppAccountsReportData();

        return $this->exportToExcel($data->toArray(), 'cuentas_aplicacion');
    }

    /**
     * Export audit logs to Excel.
     * 
     * GET /api/reports/audit/export
     */
    public function exportAudit(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        [$tenantId, $role] = $this->resolveAuditContext($request, $user);

        if (!in_array($role, self::AUDIT_ROLES, true)) {
            abort(403, 'No autorizado');
        }

        $filters = [
            'tenant_id' => $tenantId,
            'user_id' => $request->query('user_id'),
            'action' => $request->query('action'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'per_page' => 10000, // Get all for export
        ];

        $logs = $this->reportsService->getAuditLogs($filters);

        $data = collect($logs->items())->map(function ($log) {
            return [
                'id' => $log->id,
                'usuario' => $log->user?->name ?? 'Sistema',
                'email' => $log->user?->email ?? 'N/A',
                'accion' => $log->description,
                'categoria' => $log->category,
                'entidad' => $log->entity_type ? "{$log->entity_type}:{$log->entity_id}" : 'N/A',
                'ip' => $log->ip_address ?? 'N/A',
                'fecha' => $log->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return $this->exportToExcel($data, 'auditoria');
    }

    /**
     * Export batches to Excel.
     * 
     * GET /api/reports/batches/export
     */
    public function exportBatches(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);
        $this->authorizeReports($user, $tenantId);

        $filters = [
            'tenant_id' => $tenantId,
            'status' => $request->query('status'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
        ];

        $data = $this->reportsService->getBatchReportData($filters);

        return $this->exportToExcel($data->toArray(), 'lotes_carga');
    }

    /**
     * Export tenants to Excel.
     * 
     * GET /api/reports/tenants/export
     */
    public function exportTenants(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        $user = $request->user();

        // isRoot() en vez de getCurrentRole() !== 'root': determinístico y sin
        // depender del respaldo global de roles.
        if (!$user->isRoot()) {
            abort(403, 'No autorizado');
        }

        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ];

        $data = $this->reportsService->getTenantReportData($filters);

        return $this->exportToExcel($data->toArray(), 'organizaciones');
    }

    /**
     * Helper to get tenant ID based on user role.
     * 
     * - For root users: use tenant_id from query or null (all tenants)
     * - For admin users: use tenant_id from query (must belong to user) or primary tenant
     * - For other users: use primary tenant only
     */
    /**
     * Resuelve la empresa activa y el rol del usuario EN esa empresa, para el
     * módulo de auditoría.
     *
     * A diferencia de getCurrentRole() sin argumentos —que cae al respaldo
     * global `user_roles`, la UNIÓN de los roles del usuario en TODAS sus
     * empresas (ver UserService::syncGlobalRoleFallback) y además resuelve con
     * ->first() sin ORDER BY, o sea de forma no determinística—, aquí el rol se
     * resuelve estrictamente contra la empresa activa (header X-Tenant-Ids, ya
     * validado por el middleware TenantFilter en `_tenant_filter_ids`).
     *
     * Sin esto, un usuario que es admin en una empresa podía leer la auditoría
     * de otra en la que solo es aprobador (fuga de privilegios entre empresas).
     *
     * @return array{0: ?int, 1: ?string} [tenantId, role]
     */
    private function resolveAuditContext(Request $request, $user): array
    {
        $tenantId = $this->activeTenantResolver->resolve($request, $user);

        // Root es global: puede consultar una empresa concreta o todas.
        if ($user->isRoot()) {
            return [$tenantId, 'root'];
        }

        if (!$tenantId) {
            return [null, null];
        }

        // Rol dentro de esa empresa (user_tenant_roles). Sin respaldo global:
        // si no tiene rol ahí, queda null -> no autorizado.
        return [$tenantId, User::roleForTenant($user, $tenantId)];
    }

    /**
     * Roles autorizados para el módulo de auditoría, según la Matriz de Accesos
     * (docs/sprintfix/Matriz de Accesos.xlsx → "AUDITORÍA Y REPORTES"):
     * root y admin; admin_tenant solo en las organizaciones que gestiona.
     * Cliente y aprobador NO tienen acceso.
     */
    private const AUDIT_ROLES = ['root', 'admin', 'admin_tenant'];

    /**
     * Gate del módulo de reportes, según la Matriz de Accesos
     * ("AUDITORÍA Y REPORTES": root y admin; admin_tenant en las empresas que
     * gestiona; cliente y aprobador NO). Se resuelve con las abilities de
     * dashboard que ya existen en config/access_matrix.php — no se inventan
     * abilities nuevas.
     *
     * No aplica a myStats(): ese endpoint opera sobre $request->user() y ya
     * está scopeado a los datos propios del usuario, no hay fuga que cerrar.
     */
    private function authorizeReports($user, ?int $tenantId): void
    {
        if (!$user->hasAbility('dashboard.global_metrics', $tenantId)
            && !$user->hasAbility('dashboard.org_metrics', $tenantId)) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * Empresa a la que se scopean los reportes.
     *
     * La resolución de la empresa activa la hace ActiveTenantResolver, que es
     * el estándar del resto de controllers (DocumentController, etc.) para
     * endpoints de listado/dashboard/reporte. Aquí solo se aplica el matiz
     * propio de reportes:
     *
     * Root recibe null cuando no pide una empresa concreta (= todas). Un
     * no-root sin empresa recibe el centinela -1 en vez de null, porque
     * ReportsService trata null como "todas las empresas" y devolverlo aquí
     * expondría la plataforma entera.
     */
    private function getTenantId(Request $request, $user): ?int
    {
        $tenantId = $this->activeTenantResolver->resolve($request, $user);

        if ($user->isRoot()) {
            return $tenantId;
        }

        return $tenantId ?? -1;
    }

    /**
     * Export data to Excel (.xlsx format) using maatwebsite/excel.
     */
    private function exportToExcel(array $data, string $filename): BinaryFileResponse|Response|JsonResponse
    {
        if (empty($data)) {
            return response()->json([
                'message' => 'No hay datos para exportar'
            ], 404);
        }

        $collection = collect($data);
        $fullFilename = $filename . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new GenericExport($collection),
            $fullFilename
        );
    }
}
