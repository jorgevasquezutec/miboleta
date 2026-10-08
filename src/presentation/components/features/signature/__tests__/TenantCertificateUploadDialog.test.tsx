import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TenantCertificateStatus } from '@/core/domain/entities';
import { TenantCertificateUploadDialog } from '../TenantCertificateUploadDialog';

const uploadTenantCertificate = vi.fn();

vi.mock('@/presentation/stores', () => ({
  useSignatureSettingsStore: (selector: (s: unknown) => unknown) =>
    selector({ uploadTenantCertificate, savingTenantId: null }),
}));

const tenant: TenantCertificateStatus = {
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
    certificateOrganization: 'ORG',
    certificateExpiresAt: '2028-09-21T15:51:00.000000Z',
    tsaUrl: 'https://tsa.empresa.example/tsr',
    uploadedAt: null,
    uploadedBy: null,
  },
  rucMismatch: false,
  warnings: [],
};

function fill() {
  fireEvent.change(screen.getByLabelText(/Archivo del certificado/), {
    target: { files: [new File(['x'], 'firma.pfx')] },
  });
  fireEvent.change(screen.getByLabelText('Contraseña del certificado'), { target: { value: 'secret' } });
}

describe('TenantCertificateUploadDialog', () => {
  beforeEach(() => vi.clearAllMocks());

  it('prellena la TSA actual de la empresa y la envía al renovar', async () => {
    uploadTenantCertificate.mockResolvedValue({ message: 'ok', item: { rucMismatch: false } });
    render(<TenantCertificateUploadDialog tenant={tenant} open onOpenChange={() => {}} />);
    const input = screen.getByLabelText('URL de sello de tiempo (TSA)') as HTMLInputElement;
    expect(input.value).toBe('https://tsa.empresa.example/tsr');
    fill();
    fireEvent.click(screen.getByRole('button', { name: /Cargar Certificado/ }));
    await waitFor(() =>
      expect(uploadTenantCertificate).toHaveBeenCalledWith(
        7,
        expect.objectContaining({ password: 'secret', tsaUrl: 'https://tsa.empresa.example/tsr' })
      )
    );
  });

  it('permite vaciar la TSA (se envía undefined)', async () => {
    uploadTenantCertificate.mockResolvedValue({ message: 'ok', item: { rucMismatch: false } });
    render(<TenantCertificateUploadDialog tenant={tenant} open onOpenChange={() => {}} />);
    fireEvent.change(screen.getByLabelText('URL de sello de tiempo (TSA)'), { target: { value: '' } });
    fill();
    fireEvent.click(screen.getByRole('button', { name: /Cargar Certificado/ }));
    await waitFor(() => expect(uploadTenantCertificate).toHaveBeenCalled());
    expect(uploadTenantCertificate.mock.calls[0][1].tsaUrl).toBeUndefined();
  });

  it('valida que la TSA sea una URL http/https y bloquea el envío', () => {
    render(<TenantCertificateUploadDialog tenant={tenant} open onOpenChange={() => {}} />);
    fireEvent.change(screen.getByLabelText('URL de sello de tiempo (TSA)'), { target: { value: 'tsa.example' } });
    fill();
    expect(screen.getByText(/empiece con http:\/\/ o https:\/\//)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Cargar Certificado/ })).toBeDisabled();
    expect(uploadTenantCertificate).not.toHaveBeenCalled();
  });

  it('muestra la ayuda de la TSA global cuando no hay error', () => {
    render(<TenantCertificateUploadDialog tenant={{ ...tenant, certificate: null }} open onOpenChange={() => {}} />);
    expect(screen.getByText(/Si lo dejas vacío se usa la TSA global de la plataforma/)).toBeInTheDocument();
  });
});
