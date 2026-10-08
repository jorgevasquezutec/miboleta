import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useSignatureSettingsStore } from '../signatureSettingsStore';
import { TenantCertificateStatus } from '@/core/domain/entities';

vi.mock('@/infrastructure/persistence/repositories', () => ({
  signatureSettingsRepository: {
    getSettings: vi.fn(),
    uploadCertificate: vi.fn(),
    updateEnabled: vi.fn(),
    deleteCertificate: vi.fn(),
    listTenantCertificates: vi.fn(),
    uploadTenantCertificate: vi.fn(),
    deleteTenantCertificate: vi.fn(),
  },
}));

import { signatureSettingsRepository as repo } from '@/infrastructure/persistence/repositories';

const item: TenantCertificateStatus = {
  tenantId: 5,
  tenantName: 'Overhead',
  tenantRuc: '20603839961',
  tenantBusinessName: null,
  tenantStatus: 'active',
  certificateSource: 'global',
  hasOwnCertificate: false,
  certificate: null,
  rucMismatch: false,
  warnings: [],
};
const summary = { total: 1, currentPage: 1, lastPage: 1, perPage: 10, withOwnCertificate: 0, withWarnings: 0, globalHasCertificate: true };

describe('signatureSettingsStore - certificados por empresa', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useSignatureSettingsStore.setState({
      tenantCertificates: [],
      tenantSummary: null,
      isLoadingTenants: false,
      savingTenantId: null,
      tenantError: null,
      lastTenantFilters: {},
      error: null,
    });
    vi.mocked(repo.listTenantCertificates).mockResolvedValue({ items: [item], summary });
  });

  it('fetchTenantCertificates llena lista y resumen', async () => {
    await useSignatureSettingsStore.getState().fetchTenantCertificates();
    const state = useSignatureSettingsStore.getState();
    expect(state.tenantCertificates).toEqual([item]);
    expect(state.tenantSummary).toEqual(summary);
    expect(state.isLoadingTenants).toBe(false);
  });

  it('descarta respuestas obsoletas cuando resuelven en orden inverso', async () => {
    const other = { ...item, tenantId: 9, tenantName: 'Otra' };
    let resolveFirst!: (v: { items: TenantCertificateStatus[]; summary: typeof summary }) => void;
    vi.mocked(repo.listTenantCertificates)
      .mockImplementationOnce(() => new Promise((r) => { resolveFirst = r; }))
      .mockResolvedValueOnce({ items: [other], summary });
    const { fetchTenantCertificates } = useSignatureSettingsStore.getState();
    const first = fetchTenantCertificates({ onlyWarnings: true });
    await fetchTenantCertificates({ onlyWarnings: false });
    resolveFirst({ items: [item], summary });
    await first;
    expect(useSignatureSettingsStore.getState().tenantCertificates).toEqual([other]);
  });

  it('recuerda los filtros y los reusa al llamar sin argumentos', async () => {
    const { fetchTenantCertificates } = useSignatureSettingsStore.getState();
    await fetchTenantCertificates({ search: 'abc', onlyWarnings: true });
    expect(useSignatureSettingsStore.getState().lastTenantFilters).toEqual({ search: 'abc', onlyWarnings: true });

    await fetchTenantCertificates();
    expect(repo.listTenantCertificates).toHaveBeenLastCalledWith({ search: 'abc', onlyWarnings: true });
  });

  it('recuerda page/perPage y los reusa al refetch sin argumentos', async () => {
    const { fetchTenantCertificates } = useSignatureSettingsStore.getState();
    await fetchTenantCertificates({ search: 'a', page: 2, perPage: 25 });
    expect(useSignatureSettingsStore.getState().lastTenantFilters).toEqual({ search: 'a', page: 2, perPage: 25 });
    await fetchTenantCertificates();
    expect(repo.listTenantCertificates).toHaveBeenLastCalledWith({ search: 'a', page: 2, perPage: 25 });
  });

  it('si la página quedó vacía y page > 1, retrocede a la última página válida', async () => {
    vi.mocked(repo.listTenantCertificates)
      .mockResolvedValueOnce({ items: [], summary: { ...summary, total: 10, currentPage: 3, lastPage: 1 } })
      .mockResolvedValueOnce({ items: [item], summary: { ...summary, total: 10, currentPage: 1, lastPage: 1 } });
    await useSignatureSettingsStore.getState().fetchTenantCertificates({ page: 3, perPage: 10 });
    expect(repo.listTenantCertificates).toHaveBeenCalledTimes(2);
    expect(repo.listTenantCertificates).toHaveBeenLastCalledWith({ page: 1, perPage: 10 });
    const state = useSignatureSettingsStore.getState();
    expect(state.tenantCertificates).toEqual([item]);
    expect(state.lastTenantFilters.page).toBe(1);
  });

  it('tras eliminar la última fila de la última página retrocede', async () => {
    vi.mocked(repo.deleteTenantCertificate).mockResolvedValue({ message: 'ok', item });
    await useSignatureSettingsStore.getState().fetchTenantCertificates({ page: 2, perPage: 10 });
    vi.mocked(repo.listTenantCertificates)
      .mockResolvedValueOnce({ items: [], summary: { ...summary, total: 10, currentPage: 2, lastPage: 1 } })
      .mockResolvedValueOnce({ items: [item], summary });
    await useSignatureSettingsStore.getState().deleteTenantCertificate(5);
    expect(repo.listTenantCertificates).toHaveBeenLastCalledWith({ page: 1, perPage: 10 });
  });

  it('un error de fetch setea tenantError y no el error global', async () => {
    vi.mocked(repo.listTenantCertificates).mockRejectedValue(new Error('boom'));
    await useSignatureSettingsStore.getState().fetchTenantCertificates();
    const state = useSignatureSettingsStore.getState();
    expect(state.tenantError).toBeTruthy();
    expect(state.error).toBeNull();
  });

  it('upload setea y limpia savingTenantId y refresca con los filtros activos', async () => {
    const result = { message: 'ok', item };
    let during: number | null = null;
    vi.mocked(repo.uploadTenantCertificate).mockImplementation(async () => {
      during = useSignatureSettingsStore.getState().savingTenantId;
      return result;
    });
    await useSignatureSettingsStore.getState().fetchTenantCertificates({ onlyWarnings: true });

    const returned = await useSignatureSettingsStore
      .getState()
      .uploadTenantCertificate(5, { certificate: new File(['x'], 'c.pfx'), password: 'p' });

    expect(returned).toBe(result);
    expect(during).toBe(5);
    expect(useSignatureSettingsStore.getState().savingTenantId).toBeNull();
    expect(repo.listTenantCertificates).toHaveBeenLastCalledWith({ onlyWarnings: true });
  });

  it('upload re-lanza el error, limpia savingTenantId y no toca el error global', async () => {
    vi.mocked(repo.uploadTenantCertificate).mockRejectedValue(new Error('clave incorrecta'));
    await expect(
      useSignatureSettingsStore
        .getState()
        .uploadTenantCertificate(5, { certificate: new File(['x'], 'c.pfx'), password: 'p' })
    ).rejects.toThrow('clave incorrecta');
    const state = useSignatureSettingsStore.getState();
    expect(state.savingTenantId).toBeNull();
    expect(state.error).toBeNull();
    expect(state.tenantError).toBeNull();
  });

  it('delete re-lanza el error sin tocar el error global', async () => {
    vi.mocked(repo.deleteTenantCertificate).mockRejectedValue(new Error('no tiene'));
    await expect(useSignatureSettingsStore.getState().deleteTenantCertificate(5)).rejects.toThrow('no tiene');
    const state = useSignatureSettingsStore.getState();
    expect(state.savingTenantId).toBeNull();
    expect(state.error).toBeNull();
  });

  it('delete exitoso refresca la lista', async () => {
    vi.mocked(repo.deleteTenantCertificate).mockResolvedValue({ message: 'ok', item });
    await useSignatureSettingsStore.getState().deleteTenantCertificate(5);
    expect(repo.listTenantCertificates).toHaveBeenCalledTimes(1);
  });
});
