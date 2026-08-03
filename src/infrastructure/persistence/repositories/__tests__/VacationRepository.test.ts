import { describe, it, expect, vi, beforeEach } from 'vitest';

// vi.mock is hoisted, so the factory can't reference outer variables.
vi.mock('@/infrastructure/http/apiClient', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  getErrorMessage: vi.fn(),
}));

import apiClient from '@/infrastructure/http/apiClient';
import { VacationRepository } from '../VacationRepository';

/**
 * SPEC-VACACIONES v2 (31/07/2026 — SUPERSEDE el mapeo original de la Fase 2):
 * el endpoint /vacation-requests/balance trae los 4 conceptos del cliente
 * (pending, taken, truncated, balance) además de los conteos de solicitudes
 * por estado. Estos tests cubren el mapeo snake_case -> camelCase de
 * VacationRepository.getBalance(), incluidos los defaults seguros cuando el
 * backend no manda esos campos (evita que "Mis Vacaciones" reviente).
 */
describe('VacationRepository.getBalance', () => {
  let repository: VacationRepository;

  beforeEach(() => {
    vi.clearAllMocks();
    repository = new VacationRepository();
  });

  it('maps the 4 client figures and request counts from the API response', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: {
          tenant_id: 1,
          labor_regime: 'general',
          days_per_year: 30,
          initial: 0,
          accrued: 60,
          taken: 7,
          available: 53,
          pending: 60,
          truncated: 15.08,
          balance: 68.08,
          hire_date: '2024-01-31',
          years_of_service: 2,
          months_completed: 6,
          current_period_start: '2026-01-31',
          current_period_end: '2027-01-31',
          approver: null,
          requests: { pending: 11, approved: 2 },
        },
      },
    } as any);

    const balance = await repository.getBalance(1);

    expect(balance.pending).toBe(60);
    expect(balance.truncated).toBe(15.08);
    expect(balance.balance).toBe(68.08);
    expect(balance.monthsCompleted).toBe(6);
    expect(balance.currentPeriodStart).toBe('2026-01-31');
    expect(balance.currentPeriodEnd).toBe('2027-01-31');
    expect(balance.requests).toEqual({ pending: 11, approved: 2 });
    // Los campos preexistentes siguen mapeándose igual.
    expect(balance.available).toBe(53);
    expect(balance.taken).toBe(7);
  });

  it('defaults the new fields safely when the backend omits them', async () => {
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: {
          tenant_id: 1,
          labor_regime: 'general',
          days_per_year: 30,
          initial: 0,
          accrued: 0,
          taken: 0,
          available: 0,
          hire_date: null,
          years_of_service: 0,
          approver: null,
          // Sin pending/truncated/balance/months_completed/current_period_* ni requests.
        },
      },
    } as any);

    const balance = await repository.getBalance(1);

    expect(balance.pending).toBe(0);
    expect(balance.truncated).toBe(0);
    expect(balance.balance).toBe(0);
    expect(balance.monthsCompleted).toBe(0);
    expect(balance.currentPeriodStart).toBeNull();
    expect(balance.currentPeriodEnd).toBeNull();
    expect(balance.requests).toEqual({ pending: 0, approved: 0 });
  });
});
