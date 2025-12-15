<?php

namespace App\Http\Controllers\Api;

use App\Exports\GenericExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        private ReportsService $reportsService
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

        $stats = $this->reportsService->getDashboardStats($tenantId);

        return response()->json([
            'data' => $stats,
            'tenant_id' => $tenantId,
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
        $role = $user->getCurrentRole();

        // Only root and admin can view audit logs
        if (!in_array($role, ['root', 'admin'])) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $filters = [
            'tenant_id' => $this->getTenantId($request, $user),
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
        $actions = [
            'user' => [
                AuditLog::ACTION_USER_LOGIN => 'Inicio de sesión',
                AuditLog::ACTION_USER_LOGOUT => 'Cierre de sesión',
                AuditLog::ACTION_USER_LOGIN_FAILED => 'Inicio fallido',
                AuditLog::ACTION_PASSWORD_CHANGED => 'Cambio de contraseña',
                AuditLog::ACTION_USER_CREATED => 'Usuario creado',
                AuditLog::ACTION_USER_UPDATED => 'Usuario actualizado',
                AuditLog::ACTION_USER_DELETED => 'Usuario eliminado',
            ],
            'document' => [
                AuditLog::ACTION_DOCUMENT_UPLOADED => 'Documento cargado',
                AuditLog::ACTION_DOCUMENT_VIEWED => 'Documento visualizado',
                AuditLog::ACTION_DOCUMENT_DOWNLOADED => 'Documento descargado',
                AuditLog::ACTION_DOCUMENT_SIGNED => 'Documento firmado',
                AuditLog::ACTION_DOCUMENT_DELETED => 'Documento eliminado',
                AuditLog::ACTION_BATCH_CREATED => 'Lote creado',
                AuditLog::ACTION_BATCH_COMPLETED => 'Lote completado',
            ],
            'vacation' => [
                AuditLog::ACTION_VACATION_REQUESTED => 'Vacaciones solicitadas',
                AuditLog::ACTION_VACATION_APPROVED => 'Vacaciones aprobadas',
                AuditLog::ACTION_VACATION_REJECTED => 'Vacaciones rechazadas',
                AuditLog::ACTION_VACATION_CONFIRMED => 'Vacaciones confirmadas',
                AuditLog::ACTION_VACATION_CANCELLED => 'Vacaciones canceladas',
            ],
            'tenant' => [
                AuditLog::ACTION_TENANT_CREATED => 'Organización creada',
                AuditLog::ACTION_TENANT_UPDATED => 'Organización actualizada',
                AuditLog::ACTION_TENANT_DELETED => 'Organización eliminada',
            ],
        ];

        return response()->json(['data' => $actions]);
    }

    /**
     * Export documents to Excel.
     * 
     * GET /api/reports/documents/export
     */
    public function exportDocuments(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);

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
    public function exportVacations(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);

        $filters = [
            'tenant_id' => $tenantId,
            'status' => $request->query('status'),
            'year' => $request->query('year'),
            'user_id' => $request->query('user_id'),
        ];

        $data = $this->reportsService->getVacationReportData($filters);

        return $this->exportToExcel($data->toArray(), 'vacaciones');
    }

    /**
     * Export users to Excel.
     * 
     * GET /api/reports/users/export
     */
    public function exportUsers(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);

        $filters = [
            'tenant_id' => $tenantId,
            'is_active' => $request->query('is_active'),
        ];

        $data = $this->reportsService->getUsersReportData($filters);

        return $this->exportToExcel($data->toArray(), 'usuarios');
    }

    /**
     * Export audit logs to Excel.
     * 
     * GET /api/reports/audit/export
     */
    public function exportAudit(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $role = $user->getCurrentRole();

        if (!in_array($role, ['root', 'admin'])) {
            abort(403, 'No autorizado');
        }

        $tenantId = $this->getTenantId($request, $user);

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
    public function exportBatches(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $tenantId = $this->getTenantId($request, $user);

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
    public function exportTenants(Request $request): BinaryFileResponse|Response
    {
        $user = $request->user();
        $role = $user->getCurrentRole();

        if ($role !== 'root') {
            abort(403, 'No autorizado');
        }

        $filters = [
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
    private function getTenantId(Request $request, $user): ?int
    {
        $role = $user->getCurrentRole();
        $requestedTenantId = $request->query('tenant_id');

        // Root users can query any tenant (or all if not specified)
        if ($role === 'root') {
            return $requestedTenantId ? (int) $requestedTenantId : null;
        }

        // Admin users can query tenants they belong to
        if ($role === 'admin' && $requestedTenantId) {
            // Verify user belongs to the requested tenant
            if ($user->belongsToTenant((int) $requestedTenantId)) {
                return (int) $requestedTenantId;
            }
        }

        // Default: use primary tenant
        $primaryTenant = $user->primaryTenant();
        return $primaryTenant?->id;
    }

    /**
     * Export data to Excel (.xlsx format) using maatwebsite/excel.
     */
    private function exportToExcel(array $data, string $filename): BinaryFileResponse|Response
    {
        if (empty($data)) {
            return response('No hay datos para exportar', 404);
        }

        $collection = collect($data);
        $fullFilename = $filename . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new GenericExport($collection),
            $fullFilename
        );
    }
}
