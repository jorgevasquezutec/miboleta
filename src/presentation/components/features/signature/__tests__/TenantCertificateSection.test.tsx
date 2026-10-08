import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { TenantCertificateStatus } from '@/core/domain/entities';
import { signatureSettingsRepository } from '@/infrastructure/persistence/repositories';
import { TenantCertificateSection } from '../TenantCertificateSection';

vi.mock('@/infrastructure/persistence/repositories', () => ({
  signatureSettingsRepository: {
    getTenantCertificate: vi.fn(),
    previewCertificate: vi.fn(),
    uploadTenantCertificate: vi.fn(),
    deleteTenantCertificate: vi.fn(),
  },
}));

const own: TenantCertificateStatus = {
  tenantId: 7,
  tenantName: 'Overhead Men',
  tenantRuc: '20603839961',
  tenantBusinessName: null,
  tenantStatus: 'active',
  certificateSource: 'tenant',
  hasOwnCertificate: true,
  certificate: {
    certificateSubject: 'CN',
    certificateRuc: '20603839961',
    certificateOrganization: 'OVERHEAD MEN S.A.C.',
    certificateExpiresAt: '2028-09-21T15:51:00.000000Z',
    tsaUrl: null,
    uploadedAt: '2026-10-06T15:00:00.000000Z',
    uploadedBy: null,
  },
  rucMismatch: false,
  warnings: [],
};

function CreateHarness() {
  const [file, setFile] = useState<File | null>(null);
  const [password, setPassword] = useState('');
  const [tsaUrl, setTsaUrl] = useState('');
  const [error, setError] = useState<string | null>(null);
  return (
    <TenantCertificateSection
      tenantRuc="20603839961"
      file={file}
      password={password}
      onFileChange={setFile}
      onPasswordChange={setPassword}
      tsaUrl={tsaUrl}
      onTsaUrlChange={setTsaUrl}
      error={error}
      onErrorChange={setError}
    />
  );
}

describe('TenantCertificateSection', () => {
  beforeEach(() => vi.clearAllMocks());

  it('modo edición muestra Propio y el botón Renovar certificado', async () => {
    vi.mocked(signatureSettingsRepository.getTenantCertificate).mockResolvedValue(own);
    render(<TenantCertificateSection tenantId="7" />);
    expect(await screen.findByText('Propio')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Renovar certificado/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Eliminar certificado' })).toBeInTheDocument();
    expect(signatureSettingsRepository.getTenantCertificate).toHaveBeenCalledWith(7);
  });

  it('modo edición muestra la TSA propia o "Usa la TSA global"', async () => {
    vi.mocked(signatureSettingsRepository.getTenantCertificate).mockResolvedValue({
      ...own,
      certificate: { ...own.certificate!, tsaUrl: 'https://tsa.empresa.example/tsr' },
    });
    const { unmount } = render(<TenantCertificateSection tenantId="7" />);
    expect(await screen.findByText('Sello de tiempo (TSA)')).toBeInTheDocument();
    expect(screen.getByText('https://tsa.empresa.example/tsr')).toBeInTheDocument();
    unmount();

    vi.mocked(signatureSettingsRepository.getTenantCertificate).mockResolvedValue(own);
    render(<TenantCertificateSection tenantId="7" />);
    expect(await screen.findByText('Usa la TSA global')).toBeInTheDocument();
  });

  it('modo creación muestra el campo TSA opcional con su ayuda y avisa si no es URL', () => {
    render(<CreateHarness />);
    const input = screen.getByLabelText('URL de sello de tiempo (TSA)');
    expect(screen.getByText(/Si lo dejas vacío se usa la TSA global/)).toBeInTheDocument();
    fireEvent.change(input, { target: { value: 'ftp://x' } });
    expect(screen.getByText(/empiece con http:\/\/ o https:\/\//)).toBeInTheDocument();
  });

  it('modo edición sin certificado propio ofrece Cargar certificado', async () => {
    vi.mocked(signatureSettingsRepository.getTenantCertificate).mockResolvedValue({
      ...own,
      certificateSource: 'global',
      hasOwnCertificate: false,
      certificate: null,
    });
    render(<TenantCertificateSection tenantId="7" />);
    expect(await screen.findByText('Global (por defecto)')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Cargar certificado/ })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Eliminar certificado' })).toBeNull();
  });

  it('modo creación exige contraseña cuando hay archivo', async () => {
    render(<CreateHarness />);
    const file = new File(['x'], 'firma.pfx');
    fireEvent.change(screen.getByLabelText(/Archivo del certificado/), { target: { files: [file] } });
    fireEvent.click(await screen.findByRole('button', { name: /Verificar certificado/ }));
    expect(await screen.findByText('Ingresa la contraseña del certificado')).toBeInTheDocument();
    expect(signatureSettingsRepository.previewCertificate).not.toHaveBeenCalled();
  });

  it('modo creación verifica con el RUC del formulario y avisa del desajuste', async () => {
    vi.mocked(signatureSettingsRepository.previewCertificate).mockResolvedValue({
      certificateSubject: 'CN',
      certificateRuc: '20100000001',
      certificateOrganization: 'ORG',
      certificateExpiresAt: '2028-09-21T15:51:00.000000Z',
      rucMismatch: true,
      warnings: [{ code: 'ruc_mismatch', message: 'no coincide' }],
    });
    render(<CreateHarness />);
    fireEvent.change(screen.getByLabelText(/Archivo del certificado/), {
      target: { files: [new File(['x'], 'firma.pfx')] },
    });
    fireEvent.change(screen.getByLabelText('Contraseña del certificado'), { target: { value: 'secret' } });
    fireEvent.click(await screen.findByRole('button', { name: /Verificar certificado/ }));
    await waitFor(() =>
      expect(signatureSettingsRepository.previewCertificate).toHaveBeenCalledWith(
        expect.objectContaining({ password: 'secret', ruc: '20603839961' })
      )
    );
    expect(await screen.findByText(/no coincide con el RUC de la empresa/)).toBeInTheDocument();
  });
});
