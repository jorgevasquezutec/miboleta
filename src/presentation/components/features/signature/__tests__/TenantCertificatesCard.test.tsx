import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { useSignatureSettingsStore } from '@/presentation/stores/signatureSettingsStore';
import { TenantCertificateStatus } from '@/core/domain/entities';
import { TenantCertificatesCard } from '../TenantCertificatesCard';

vi.mock('@/infrastructure/persistence/repositories', () => ({
  signatureSettingsRepository: {
    listTenantCertificates: vi.fn(),
    uploadTenantCertificate: vi.fn(),
    deleteTenantCertificate: vi.fn(),
  },
}));

// El debounce se anula para que el test sea síncrono.
vi.mock('@/presentation/hooks/useDebounce', () => ({ useDebounce: <T,>(v: T) => v }));

const base: TenantCertificateStatus = {
  tenantId: 1,
  tenantName: 'Empresa Propia',
  tenantRuc: '20603839961',
  tenantBusinessName: null,
  tenantStatus: 'active',
  certificateSource: 'tenant',
  hasOwnCertificate: true,
  certificate: {
    certificateSubject: 'CN',
    certificateRuc: '20100000001',
    certificateOrganization: 'ORG',
    certificateExpiresAt: '2028-09-21T15:51:00.000000Z',
    tsaUrl: null,
    uploadedAt: null,
    uploadedBy: null,
  },
  rucMismatch: true,
  warnings: [{ code: 'ruc_mismatch', message: 'El RUC no coincide' }],
};
const global: TenantCertificateStatus = {
  ...base,
  tenantId: 2,
  tenantName: 'Empresa Global',
  certificateSource: 'global',
  hasOwnCertificate: false,
  certificate: null,
  rucMismatch: false,
  warnings: [],
};

describe('TenantCertificatesCard', () => {
  const fetchMock = vi.fn().mockResolvedValue(undefined);
  const deleteMock = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    useSignatureSettingsStore.setState({
      tenantCertificates: [base, global],
      tenantSummary: { total: 12, currentPage: 2, lastPage: 2, perPage: 10, withOwnCertificate: 1, withWarnings: 1, globalHasCertificate: true },
      isLoadingTenants: false,
      savingTenantId: null,
      tenantError: null,
      lastTenantFilters: {},
      fetchTenantCertificates: fetchMock,
      deleteTenantCertificate: deleteMock,
    });
  });

  it('muestra los badges Propio, Global (por defecto) y RUC no coincide', () => {
    render(<TenantCertificatesCard />);
    expect(screen.getByText('Propio')).toBeInTheDocument();
    expect(screen.getByText('Global (por defecto)')).toBeInTheDocument();
    expect(screen.getByText('RUC no coincide')).toBeInTheDocument();
    expect(screen.getByText('1 con certificado propio')).toBeInTheDocument();
  });

  it('el switch "Solo con avisos" consulta con onlyWarnings: true', async () => {
    render(<TenantCertificatesCard />);
    fireEvent.click(screen.getByRole('switch', { name: 'Solo con avisos' }));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenCalledWith({ search: undefined, onlyWarnings: true, page: 1, perPage: undefined })
    );
  });

  it('renderiza el paginador y pide la página nueva conservando filtros', () => {
    useSignatureSettingsStore.setState({ lastTenantFilters: { search: 'x', page: 2, perPage: 10 } });
    render(<TenantCertificatesCard />);
    expect(screen.getByText(/12/)).toBeInTheDocument();
    fireEvent.click(screen.getByLabelText('Ir a la página anterior'));
    expect(fetchMock).toHaveBeenCalledWith({ search: 'x', page: 1, perPage: 10 });
  });

  it('no renderiza el paginador si total es 0', () => {
    useSignatureSettingsStore.setState({
      tenantSummary: { total: 0, currentPage: 1, lastPage: 1, perPage: 10, withOwnCertificate: 0, withWarnings: 0, globalHasCertificate: true },
    });
    render(<TenantCertificatesCard />);
    expect(screen.queryByLabelText('Ir a la página anterior')).not.toBeInTheDocument();
  });

  it('"Eliminar" abre el ConfirmDialog', () => {
    render(<TenantCertificatesCard />);
    fireEvent.click(screen.getByRole('button', { name: /Eliminar/ }));
    expect(
      screen.getByText('¿Eliminar el certificado de Empresa Propia? Sus documentos se firmarán con el certificado global.')
    ).toBeInTheDocument();
  });
});
