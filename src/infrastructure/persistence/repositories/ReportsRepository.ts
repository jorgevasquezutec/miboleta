import apiClient, { toApiError } from '@/infrastructure/http/apiClient';
import {
    DashboardStats,
    DocumentStats,
    VacationStats,
    UserStats,
    ActivityEntry,
    AuditActions,
    ReportFilters,
    PaginatedAuditResponse,
} from '@/core/domain/entities';
import { IReportsRepository } from '@/core/domain/repositories/IReportsRepository';

/**
 * Los exports piden `responseType: 'blob'`, así que cuando el backend responde
 * con un error el JSON viaja DENTRO del Blob y hay que leerlo para sacar el
 * mensaje. Devuelve null si el Blob no trae un JSON con mensaje, para que el
 * llamador caiga al error ya normalizado.
 */
async function blobErrorMessage(blob: Blob): Promise<string | null> {
    try {
        const json = JSON.parse(await blob.text());
        return json.message || json.error || null;
    } catch {
        return null;
    }
}

/**
 * Reports Repository
 * Handles API calls for dashboard statistics, reports, and exports.
 */
export class ReportsRepository implements IReportsRepository {
    /**
     * Get complete dashboard statistics
     */
    async getDashboardStats(tenantId?: number, startDate?: string, endDate?: string): Promise<DashboardStats> {
        try {
            const params: Record<string, unknown> = {};
            if (tenantId) params.tenant_id = tenantId;
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;

            const response = await apiClient.get<{ data: DashboardStats }>('/reports/dashboard', { params });
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get document statistics
     */
    async getDocumentStats(tenantId?: number): Promise<DocumentStats> {
        try {
            const params = tenantId ? { tenant_id: tenantId } : {};
            const response = await apiClient.get<{ data: DocumentStats }>('/reports/documents', { params });
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get vacation statistics
     */
    async getVacationStats(tenantId?: number): Promise<VacationStats> {
        try {
            const params = tenantId ? { tenant_id: tenantId } : {};
            const response = await apiClient.get<{ data: VacationStats }>('/reports/vacations', { params });
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get user statistics
     */
    async getUserStats(tenantId?: number): Promise<UserStats> {
        try {
            const params = tenantId ? { tenant_id: tenantId } : {};
            const response = await apiClient.get<{ data: UserStats }>('/reports/users', { params });
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get recent activity
     */
    async getRecentActivity(tenantId?: number, limit = 10): Promise<ActivityEntry[]> {
        try {
            const params: Record<string, unknown> = { limit };
            if (tenantId) params.tenant_id = tenantId;

            const response = await apiClient.get<{ data: ActivityEntry[] }>('/reports/activity', { params });
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get audit logs with pagination and filters
     */
    async getAuditLogs(filters: ReportFilters = {}): Promise<PaginatedAuditResponse> {
        try {
            const response = await apiClient.get<PaginatedAuditResponse>('/reports/audit', { params: filters });
            return response.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Get available audit actions for filtering
     */
    async getAuditActions(): Promise<AuditActions> {
        try {
            const response = await apiClient.get<{ data: AuditActions }>('/reports/audit/actions');
            return response.data.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Export documents to Excel
     */
    async exportDocuments(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/documents/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Export vacations to Excel
     */
    async exportVacations(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/vacations/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error: unknown) {
            const apiError = toApiError(error);

            // El payload del error viene como Blob (responseType: 'blob')
            if (apiError.data instanceof Blob) {
                const message = await blobErrorMessage(apiError.data);
                if (message) throw new Error(message);
            }

            throw apiError;
        }
    }

    /**
     * Export users to Excel
     */
    async exportUsers(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/users/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error: unknown) {
            const apiError = toApiError(error);

            // El payload del error viene como Blob (responseType: 'blob')
            if (apiError.data instanceof Blob) {
                const message = await blobErrorMessage(apiError.data);
                if (message) throw new Error(message);
            }

            throw apiError;
        }
    }

    /**
     * Export app accounts (cuentas de aplicación) to Excel. Sin filtros:
     * ReportsController::exportAppAccounts scopea a la empresa activa del
     * backend (root/admin_tenant vía header), no recibe params.
     */
    async exportAppAccounts(): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/app-accounts/export', {
                responseType: 'blob',
            });
            return response.data;
        } catch (error: unknown) {
            const apiError = toApiError(error);

            // El payload del error viene como Blob (responseType: 'blob')
            if (apiError.data instanceof Blob) {
                const message = await blobErrorMessage(apiError.data);
                if (message) throw new Error(message);
            }

            throw apiError;
        }
    }

    /**
     * Export audit logs to Excel
     */
    async exportAudit(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/audit/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Export batches to Excel
     */
    async exportBatches(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/batches/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Export tenants to Excel
     */
    async exportTenants(filters: ReportFilters = {}): Promise<Blob> {
        try {
            const response = await apiClient.get('/reports/tenants/export', {
                params: filters,
                responseType: 'blob',
            });
            return response.data;
        } catch (error) {
            throw toApiError(error);
        }
    }

    /**
     * Helper to download a blob as a file
     */
    downloadBlob(blob: Blob, filename: string): void {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }
}

// Singleton instance
export const reportsRepository = new ReportsRepository();
