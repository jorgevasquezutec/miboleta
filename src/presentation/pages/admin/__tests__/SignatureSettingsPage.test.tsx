import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { useSignatureSettingsStore } from '@/presentation/stores/signatureSettingsStore';
import { SignatureSettingsPage } from '../SignatureSettingsPage';

vi.mock('@/infrastructure/persistence/repositories', () => ({
  signatureSettingsRepository: {},
}));
vi.mock('@/presentation/components/features/signature/TenantCertificatesCard', () => ({
  TenantCertificatesCard: () => <div data-testid="tenant-card">Tarjeta empresas</div>,
}));

const summary = (withWarnings: number) => ({
  total: 5, currentPage: 1, lastPage: 1, perPage: 10, withOwnCertificate: 1, withWarnings, globalHasCertificate: true,
});

const withCert = {
  signatureEnabled: true,
  hasCertificate: true,
  certificateSubject: 'CN',
  certificateRuc: '20100000001',
  certificateOrganization: 'ORG',
  certificateExpiresAt: '2090-01-01T00:00:00Z',
  tsaUrl: null,
  uploadedAt: null,
};

function setup(settings: unknown, warnings = 0, url = '/admin/firma') {
  useSignatureSettingsStore.setState({
    settings: settings as never,
    isLoading: false,
    isSaving: false,
    error: null,
    tenantSummary: summary(warnings) as never,
    fetchSettings: vi.fn().mockResolvedValue(undefined),
    fetchTenantCertificates: vi.fn().mockResolvedValue(undefined),
  });
  return render(
    <MemoryRouter initialEntries={[url]}>
      <SignatureSettingsPage />
    </MemoryRouter>
  );
}

describe('SignatureSettingsPage', () => {
  beforeEach(() => vi.clearAllMocks());

  it('muestra la pestaña Empresas por defecto', () => {
    setup(withCert);
    expect(screen.getByTestId('tenant-card')).toBeInTheDocument();
    expect(screen.queryByText('Activar firma digital')).not.toBeInTheDocument();
    expect(screen.getByText('Firma digital activada')).toBeInTheDocument();
  });

  it('?tab=global abre la pestaña global', () => {
    setup(withCert, 0, '/admin/firma?tab=global');
    expect(screen.getByText('Activar firma digital')).toBeInTheDocument();
  });

  it('valor de tab desconocido cae en Empresas', () => {
    setup(withCert, 0, '/admin/firma?tab=xyz');
    expect(screen.getByTestId('tenant-card')).toBeInTheDocument();
  });

  it('muestra el badge con el número de avisos', () => {
    setup(withCert, 2);
    expect(screen.getByLabelText('2 empresas con aviso')).toHaveTextContent('2');
  });

  it('sin certificado global: franja con botón que cambia a global y formulario inline', () => {
    setup({ ...withCert, hasCertificate: false, signatureEnabled: false, certificateExpiresAt: null });
    expect(screen.getByText(/No hay certificado global/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Cargar certificado global/ }));
    expect(screen.getByLabelText(/Archivo del certificado/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Renovar/ })).not.toBeInTheDocument();
  });

  it('con certificado: "Renovar / Reemplazar certificado" abre el diálogo', () => {
    setup(withCert, 0, '/admin/firma?tab=global');
    expect(screen.queryByLabelText(/Archivo del certificado/)).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Renovar \/ Reemplazar certificado/ }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByLabelText(/Archivo del certificado/)).toBeInTheDocument();
  });
});
