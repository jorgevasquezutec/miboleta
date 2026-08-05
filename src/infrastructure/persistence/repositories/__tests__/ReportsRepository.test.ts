import { describe, it, expect, vi, beforeEach } from 'vitest';

// vi.mock is hoisted, so the factory can't reference outer variables.
vi.mock('@/infrastructure/http/apiClient', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  getErrorMessage: vi.fn((error: any) => error?.message || 'Error desconocido'),
}));

import apiClient from '@/infrastructure/http/apiClient';
import { ReportsRepository } from '../ReportsRepository';

/**
 * Blob de error: Laravel manda el JSON de error DENTRO del blob (mismo
 * content-type que un export exitoso), no como response.data normal, así que
 * getErrorMessage() no lo ve. exportVacations ya desenvolvía ese JSON;
 * exportUsers y exportAppAccounts deben hacer lo mismo (SPEC 04/08/2026:
 * ability nueva `reports.app_accounts_export`, endpoint sin filtros).
 */
function makeBlobErrorResponse(payload: Record<string, unknown>) {
  const blob = {
    text: () => Promise.resolve(JSON.stringify(payload)),
  } as unknown as Blob;
  Object.setPrototypeOf(blob, Blob.prototype);
  return {
    response: { data: blob },
  };
}

describe('ReportsRepository.exportAppAccounts', () => {
  let repository: ReportsRepository;

  beforeEach(() => {
    vi.clearAllMocks();
    repository = new ReportsRepository();
  });

  it('calls GET /reports/app-accounts/export as a blob, without params', async () => {
    const fakeBlob = new Blob(['xlsx-content']);
    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: fakeBlob });

    const result = await repository.exportAppAccounts();

    expect(apiClient.get).toHaveBeenCalledWith('/reports/app-accounts/export', {
      responseType: 'blob',
    });
    expect(result).toBe(fakeBlob);
  });

  it('unwraps the JSON error message embedded in the blob (e.g. 404 sin datos)', async () => {
    vi.mocked(apiClient.get).mockRejectedValueOnce(
      makeBlobErrorResponse({ message: 'No hay datos para exportar' })
    );

    await expect(repository.exportAppAccounts()).rejects.toThrow('No hay datos para exportar');
  });

  it('falls back to the generic error message when the blob is not JSON', async () => {
    const unparsableBlob = {
      text: () => Promise.resolve('not-json'),
    } as unknown as Blob;
    Object.setPrototypeOf(unparsableBlob, Blob.prototype);

    vi.mocked(apiClient.get).mockRejectedValueOnce({
      response: { data: unparsableBlob },
      message: 'Request failed',
    });

    await expect(repository.exportAppAccounts()).rejects.toThrow();
  });
});

describe('ReportsRepository.exportUsers', () => {
  let repository: ReportsRepository;

  beforeEach(() => {
    vi.clearAllMocks();
    repository = new ReportsRepository();
  });

  it('unwraps the JSON error message embedded in the blob instead of a generic toast', async () => {
    // Antes exportUsers no desenvolvía el blob de error: con la whitelist
    // nueva, una empresa sin empleados devuelve 404 "No hay datos para
    // exportar" y el usuario debe ver ESE mensaje, no uno genérico.
    vi.mocked(apiClient.get).mockRejectedValueOnce(
      makeBlobErrorResponse({ message: 'No hay datos para exportar' })
    );

    await expect(repository.exportUsers({})).rejects.toThrow('No hay datos para exportar');
  });

  it('still exports normally when the response is a valid blob', async () => {
    const fakeBlob = new Blob(['xlsx-content']);
    vi.mocked(apiClient.get).mockResolvedValueOnce({ data: fakeBlob });

    const result = await repository.exportUsers({ search: 'juan' });

    expect(apiClient.get).toHaveBeenCalledWith('/reports/users/export', {
      params: { search: 'juan' },
      responseType: 'blob',
    });
    expect(result).toBe(fakeBlob);
  });
});
