import {
  VacationRequest,
  CreateVacationRequestDTO,
  RejectVacationRequestDTO,
  VacationBalance,
  TeamRosterMember,
} from '../entities/VacationRequest';

export interface VacationFilters {
  page?: number;
  perPage?: number;
  status?: string;
  year?: number;
  wasTaken?: 'true' | 'false' | 'pending' | 'all';
  dateFrom?: string;
  dateTo?: string;
  search?: string;
  tenantId?: number;
}

export interface PaginatedVacationRequests {
  data: VacationRequest[];
  meta: {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    // Solo presentes en getAllHistory(): conteos de "Aprobadas" / "Tomadas"
    // sobre TODO el conjunto filtrado (no solo la página actual). Ver
    // VacationService::getAllRequestsCounts en el backend.
    approvedCount?: number;
    takenCount?: number;
  };
}

export interface IVacationRepository {
  // Vacation Requests
  findAll(filters?: VacationFilters): Promise<PaginatedVacationRequests>;
  findById(id: number): Promise<VacationRequest>;
  create(data: CreateVacationRequestDTO): Promise<VacationRequest>;
  cancel(id: number): Promise<void>;
  
  // Supervisor Actions
  approve(id: number): Promise<VacationRequest>;
  reject(id: number, data: RejectVacationRequestDTO): Promise<VacationRequest>;
  markAsTaken(id: number): Promise<VacationRequest>;
  markAsNotTaken(id: number): Promise<VacationRequest>;
  
  // Supervisor Views
  getPendingApprovals(filters?: VacationFilters): Promise<PaginatedVacationRequests>;
  getPendingConfirmations(filters?: VacationFilters): Promise<PaginatedVacationRequests>;
  getMyTeam(filters?: VacationFilters): Promise<PaginatedVacationRequests>;
  getMyDecisions(filters?: VacationFilters): Promise<PaginatedVacationRequests>;
  getAllHistory(filters?: VacationFilters): Promise<PaginatedVacationRequests>;

  // Team Roster (Mi Equipo, ítem 43): directorio del personal a cargo en la
  // empresa activa (no solicitudes). La empresa la resuelve el backend igual
  // que getMyTeam() (header X-Tenant-Ids inyectado por el interceptor de
  // apiClient), sin parámetro explícito.
  getMyTeamRoster(): Promise<TeamRosterMember[]>;

  // Balance
  getBalance(tenantId?: number): Promise<VacationBalance>;
}
