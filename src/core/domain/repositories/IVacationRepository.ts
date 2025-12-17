import {
  VacationRequest,
  CreateVacationRequestDTO,
  RejectVacationRequestDTO,
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
}
