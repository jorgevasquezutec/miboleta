import apiClient, { getErrorMessage } from '@/infrastructure/http/apiClient';
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

/**
 * Reports Repository
 * Handles API calls for dashboard statistics, reports, and exports.
 */
export class ReportsRepository {
    /**
     * Get complete dashboard statistics
     */
    async getDashboardStats(tenantId?: number): Promise<DashboardStats> {
        try {
            const params = tenantId ? { tenant_id: tenantId } : {};
            const response = await apiClient.get<{ data: DashboardStats }>('/reports/dashboard', { params });
            return response.data.data;
        } catch (error) {
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
        } catch (error) {
            throw new Error(getErrorMessage(error));
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
        } catch (error) {
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
            throw new Error(getErrorMessage(error));
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
