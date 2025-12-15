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
    /**
     * Get complete dashboard statistics.
     */
    public function getDashboardStats(?int $tenantId = null): array
    {
        return [
            'documents' => $this->getDocumentStats($tenantId),
            'vacations' => $this->getVacationStats($tenantId),
            'users' => $this->getUserStats($tenantId),
            'recent_activity' => $this->getRecentActivity($tenantId, 10),
        ];
    }

    /**
     * Get document statistics.
     */
    public function getDocumentStats(?int $tenantId = null): array
    {
        $query = Document::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = (clone $query)->count();
        $signed = (clone $query)->where('status', 'signed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $active = (clone $query)->where('status', 'active')->count();

        // Documents by month (last 6 months)
        $byMonth = $this->getDocumentsByMonth($tenantId, 6);

        // Documents by type (using document_types relationship)
        $byType = Document::query()
            ->join('document_types', 'documents.doc_type_id', '=', 'document_types.id')
            ->when($tenantId, fn($q) => $q->where('documents.tenant_id', $tenantId))
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
        ];

        return [
            'total' => $total,
            'signed' => $signed,
            'pending' => $pending,
            'active' => $active,
            'by_month' => $byMonth,
            'by_type' => $byType,
            'status_distribution' => $statusDistribution,
        ];
    }

    /**
     * Get documents grouped by month.
     */
    private function getDocumentsByMonth(?int $tenantId, int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $query = Document::query()
            ->where('created_at', '>=', $startDate);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $results = $query
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('count(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Fill in missing months with zero
        $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $date = Carbon::now()->subMonths($months - 1 - $i);
            $year = $date->year;
            $month = $date->month;

            $count = $results->first(fn($r) => $r->year == $year && $r->month == $month)?->count ?? 0;

            $data[] = [
                'name' => $monthNames[$month - 1],
                'month' => $date->format('Y-m'),
                'value' => $count,
            ];
        }

        return $data;
    }

    /**
     * Get vacation statistics.
     */
    public function getVacationStats(?int $tenantId = null): array
    {
        $currentYear = Carbon::now()->year;

        $query = VacationRequest::query()
            ->whereYear('start_date', $currentYear);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $total = (clone $query)->count();
        $pending = (clone $query)->whereIn('status', ['pending', 'pending_confirmation'])->count();
        $approved = (clone $query)->whereIn('status', ['approved', 'confirmed'])->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();

        // Total days used (only approved/confirmed)
        $totalDaysUsed = (clone $query)
            ->whereIn('status', ['approved', 'confirmed', 'taken'])
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

            $users = $tenant->users();
            $total = $users->count();
            $active = $users->where('status', 'active')->count();
            $inactive = $users->whereIn('status', ['inactive', 'terminated', 'pending'])->count();

            // Users by role in this tenant
            $byRole = DB::table('user_tenants')
                ->join('users', 'user_tenants.user_id', '=', 'users.id')
                ->join('roles', 'user_tenants.role_id', '=', 'roles.id')
                ->where('user_tenants.tenant_id', $tenantId)
                ->select('roles.name', DB::raw('count(*) as count'))
                ->groupBy('roles.name')
                ->get()
                ->mapWithKeys(fn($item) => [$item->name => $item->count])
                ->toArray();
        } else {
            // Global stats
            $total = User::count();
            $active = User::where('status', 'active')->count();
            $inactive = User::whereIn('status', ['inactive', 'terminated', 'pending'])->count();

            $byRole = User::join('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->select('roles.name', DB::raw('count(*) as count'))
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
        $query = AuditLog::with('user:id,name,email')
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
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
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
     */
    public function getUsersReportData(array $filters = []): \Illuminate\Support\Collection
    {
        $query = User::with(['tenants:id,name'])
            ->orderBy('name');

        if (!empty($filters['tenant_id'])) {
            $query->whereHas('tenants', fn($q) => $q->where('tenants.id', $filters['tenant_id']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function ($user) {
            $role = $user->getCurrentRole();
            $tenantNames = $user->tenants->pluck('name')->join(', ');

            return [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'rol' => $role ?? 'N/A',
                'activo' => $user->status === 'active' ? 'Sí' : 'No',
                'estado' => $user->status ?? 'N/A',
                'organizaciones' => $tenantNames ?: 'N/A',
                'fecha_registro' => $user->created_at?->format('Y-m-d'),
                'ultimo_acceso' => $user->last_login_at?->format('Y-m-d H:i') ?? 'N/A',
            ];
        });
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
        $query = Tenant::withCount(['users', 'documents'])
            ->orderBy('name');

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
                'usuarios' => $tenant->users_count,
                'documentos' => $tenant->documents_count,
                'estado' => $tenant->status === 'active' ? 'Activo' : 'Inactivo',
                'fecha_creacion' => $tenant->created_at?->format('Y-m-d'),
            ];
        });
    }
}
