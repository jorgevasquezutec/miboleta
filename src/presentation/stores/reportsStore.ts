import { create } from 'zustand';
import {
    DashboardStats,
    DocumentStats,
    VacationStats,
    UserStats,
    ActivityEntry,
    AuditLog,
    ReportFilters,
} from '@/core/domain/entities';
import { reportsRepository } from '@/infrastructure/persistence/repositories';

interface ReportsState {
    // Dashboard data
    dashboardStats: DashboardStats | null;
    documentStats: DocumentStats | null;
    vacationStats: VacationStats | null;
    userStats: UserStats | null;
    recentActivity: ActivityEntry[];

    // Audit logs
    auditLogs: AuditLog[];
    auditPage: number;
    auditTotalPages: number;
    auditTotal: number;

    // Loading states
    isLoadingDashboard: boolean;
    isLoadingAudit: boolean;
    isExporting: boolean;

    // Error
    error: string | null;

    // Actions
    fetchDashboardStats: (tenantId?: number, startDate?: string, endDate?: string) => Promise<void>;
    fetchDocumentStats: (tenantId?: number) => Promise<void>;
    fetchVacationStats: (tenantId?: number) => Promise<void>;
    fetchUserStats: (tenantId?: number) => Promise<void>;
    fetchRecentActivity: (tenantId?: number, limit?: number) => Promise<void>;
    fetchAuditLogs: (filters?: ReportFilters) => Promise<void>;

    // Export actions
    exportDocuments: (filters?: ReportFilters) => Promise<void>;
    exportVacations: (filters?: ReportFilters) => Promise<void>;
    exportUsers: (filters?: ReportFilters) => Promise<void>;
    exportAudit: (filters?: ReportFilters) => Promise<void>;

    // Helpers
    clearError: () => void;
    reset: () => void;
}

const initialState = {
    dashboardStats: null,
    documentStats: null,
    vacationStats: null,
    userStats: null,
    recentActivity: [],
    auditLogs: [],
    auditPage: 1,
    auditTotalPages: 0,
    auditTotal: 0,
    isLoadingDashboard: false,
    isLoadingAudit: false,
    isExporting: false,
    error: null,
};

export const useReportsStore = create<ReportsState>((set) => ({
    ...initialState,

    fetchDashboardStats: async (tenantId?: number, startDate?: string, endDate?: string) => {
        set({ isLoadingDashboard: true, error: null });
        try {
            const stats = await reportsRepository.getDashboardStats(tenantId, startDate, endDate);
            set({
                dashboardStats: stats,
                documentStats: stats.documents,
                vacationStats: stats.vacations,
                userStats: stats.users,
                recentActivity: stats.recent_activity,
                isLoadingDashboard: false,
            });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar estadísticas';
            set({ error: errorMessage, isLoadingDashboard: false });
        }
    },

    fetchDocumentStats: async (tenantId?: number) => {
        set({ error: null });
        try {
            const stats = await reportsRepository.getDocumentStats(tenantId);
            set({ documentStats: stats });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar estadísticas de documentos';
            set({ error: errorMessage });
        }
    },

    fetchVacationStats: async (tenantId?: number) => {
        set({ error: null });
        try {
            const stats = await reportsRepository.getVacationStats(tenantId);
            set({ vacationStats: stats });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar estadísticas de vacaciones';
            set({ error: errorMessage });
        }
    },

    fetchUserStats: async (tenantId?: number) => {
        set({ error: null });
        try {
            const stats = await reportsRepository.getUserStats(tenantId);
            set({ userStats: stats });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar estadísticas de usuarios';
            set({ error: errorMessage });
        }
    },

    fetchRecentActivity: async (tenantId?: number, limit = 10) => {
        set({ error: null });
        try {
            const activity = await reportsRepository.getRecentActivity(tenantId, limit);
            set({ recentActivity: activity });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar actividad reciente';
            set({ error: errorMessage });
        }
    },

    fetchAuditLogs: async (filters: ReportFilters = {}) => {
        set({ isLoadingAudit: true, error: null });
        try {
            const response = await reportsRepository.getAuditLogs(filters);
            set({
                auditLogs: response.data,
                auditPage: response.meta.current_page,
                auditTotalPages: response.meta.last_page,
                auditTotal: response.meta.total,
                isLoadingAudit: false,
            });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar logs de auditoría';
            set({ error: errorMessage, isLoadingAudit: false });
        }
    },

    exportDocuments: async (filters: ReportFilters = {}) => {
        set({ isExporting: true, error: null });
        try {
            const blob = await reportsRepository.exportDocuments(filters);
            const filename = `documentos_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            set({ isExporting: false });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al exportar documentos';
            set({ error: errorMessage, isExporting: false });
        }
    },

    exportVacations: async (filters: ReportFilters = {}) => {
        set({ isExporting: true, error: null });
        try {
            const blob = await reportsRepository.exportVacations(filters);
            const filename = `vacaciones_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            set({ isExporting: false });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al exportar vacaciones';
            set({ error: errorMessage, isExporting: false });
        }
    },

    exportUsers: async (filters: ReportFilters = {}) => {
        set({ isExporting: true, error: null });
        try {
            const blob = await reportsRepository.exportUsers(filters);
            const filename = `usuarios_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            set({ isExporting: false });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al exportar usuarios';
            set({ error: errorMessage, isExporting: false });
        }
    },

    exportAudit: async (filters: ReportFilters = {}) => {
        set({ isExporting: true, error: null });
        try {
            const blob = await reportsRepository.exportAudit(filters);
            const filename = `auditoria_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            set({ isExporting: false });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al exportar auditoría';
            set({ error: errorMessage, isExporting: false });
        }
    },

    clearError: () => set({ error: null }),

    reset: () => set(initialState),
}));
