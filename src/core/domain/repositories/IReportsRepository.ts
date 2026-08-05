import {
  DashboardStats,
  DocumentStats,
  VacationStats,
  UserStats,
  ActivityEntry,
  AuditActions,
  ReportFilters,
  PaginatedAuditResponse,
} from '../entities';

export interface IReportsRepository {
  // Statistics
  getDashboardStats(tenantId?: number, startDate?: string, endDate?: string): Promise<DashboardStats>;
  getDocumentStats(tenantId?: number): Promise<DocumentStats>;
  getVacationStats(tenantId?: number): Promise<VacationStats>;
  getUserStats(tenantId?: number): Promise<UserStats>;
  
  // Activity & Audit
  getRecentActivity(tenantId?: number, limit?: number): Promise<ActivityEntry[]>;
  getAuditLogs(filters?: ReportFilters): Promise<PaginatedAuditResponse>;
  getAuditActions(): Promise<AuditActions>;
  
  // Exports
  exportDocuments(filters?: ReportFilters): Promise<Blob>;
  exportVacations(filters?: ReportFilters): Promise<Blob>;
  exportUsers(filters?: ReportFilters): Promise<Blob>;
  exportAppAccounts(): Promise<Blob>;
  exportAudit(filters?: ReportFilters): Promise<Blob>;
  exportBatches(filters?: ReportFilters): Promise<Blob>;
  exportTenants(filters?: ReportFilters): Promise<Blob>;
  
  // Helpers
  downloadBlob(blob: Blob, filename: string): void;
}
