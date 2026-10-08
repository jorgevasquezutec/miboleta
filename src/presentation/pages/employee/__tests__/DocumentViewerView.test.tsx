import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

vi.mock('@/infrastructure/persistence/repositories', () => ({
  documentRepository: {},
}));
vi.mock('@/presentation/components/shared/PDFViewer', () => ({
  PDFViewer: () => <div data-testid="pdf-viewer" />,
}));
vi.mock('@/presentation/components/features/documents/DocumentSignatureModal', () => ({
  DocumentSignatureModal: () => null,
}));

import { useDocumentsStore } from '@/presentation/stores/documentsStore';
import { useAuthStore } from '@/presentation/stores/authStore';
import { DocumentViewerView } from '../DocumentViewerView';

const pades = {
  method: 'pades_pyhanko' as const,
  signer_subject: 'CN=EMPRESA',
  signing_time: '2026-10-06T15:00:00-05:00',
  tsa_applied: true,
  certificate_source: 'tenant' as const,
};

const baseDoc = {
  id: 1, tenantId: 1, userId: 10, batchId: null, docTypeId: 1,
  employeeDocumentNumber: '1', period: '2026-09', filePath: 'x', fileSize: 1, originalName: 'x.pdf',
  status: 'pending', uploadedBy: 1, requiresSignature: true, signature: null, signedAt: null,
  digitalSignature: null, digitallySignedAt: null, digitalSignatureStatus: null, digitalSignatureError: null,
  expiresAt: null, notified: false, notifiedAt: null, version: 1,
  createdAt: '2026-10-01T00:00:00Z', updatedAt: '2026-10-01T00:00:00Z',
};

function setup(doc: Record<string, unknown>, opts: { admin?: boolean; ownerId?: number } = {}) {
  useAuthStore.setState({
    user: { id: String(opts.ownerId ?? 10) } as never,
    currentRole: opts.admin ? 'admin' : 'client',
    accessMatrix: { 'documents.sign_digital': ['root', 'admin'] },
  } as never);
  useDocumentsStore.setState({
    currentDocument: { ...baseDoc, ...doc } as never,
    isLoading: false,
    error: null,
    signatureTermsAccepted: true,
    fetchDocumentById: vi.fn().mockResolvedValue(undefined),
    checkSignatureTerms: vi.fn().mockResolvedValue(undefined),
    pollDigitalSignature: vi.fn().mockResolvedValue('timeout'),
    retryDigitalSignature: vi.fn().mockResolvedValue(undefined),
  } as never);
  return render(
    <MemoryRouter initialEntries={['/documento?id=1']}>
      <DocumentViewerView />
    </MemoryRouter>
  );
}

describe('DocumentViewerView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('PAdES + trabajador pendiente → dos tarjetas y botón Firmar', () => {
    setup({ digitalSignature: pades, digitalSignatureStatus: 'signed' });
    expect(screen.getByText('Firmado por la empresa')).toBeInTheDocument();
    expect(screen.getByText('Tu conformidad')).toBeInTheDocument();
    expect(screen.getByText('Pendiente')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Firmar documento/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Verificar firma/ })).toBeInTheDocument();
  });

  it('includes_conformity → insignia "Incluye tu conformidad"', () => {
    setup({
      status: 'signed', signedAt: '2026-10-06T15:30:00Z',
      digitalSignature: { ...pades, includes_conformity: true }, digitalSignatureStatus: 'signed',
    });
    expect(screen.getByText('Incluye tu conformidad')).toBeInTheDocument();
    expect(screen.getByText(/Firmada el/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Firmar documento/ })).toBeNull();
  });

  it('pending → aviso de actualización e inicia el polling', () => {
    setup({
      status: 'signed', signedAt: '2026-10-06T15:30:00Z',
      digitalSignature: pades, digitalSignatureStatus: 'pending',
    });
    expect(screen.getByText('Actualizando el PDF firmado…')).toBeInTheDocument();
    expect(useDocumentsStore.getState().pollDigitalSignature).toHaveBeenCalledWith(
      1, expect.objectContaining({ intervalMs: 3000, timeoutMs: 120000 })
    );
  });

  it('failed → aviso; el trabajador no ve detalle ni Reintentar', () => {
    setup({
      status: 'signed', signedAt: '2026-10-06T15:30:00Z',
      digitalSignature: pades, digitalSignatureStatus: 'failed', digitalSignatureError: null,
    });
    expect(screen.getByText('El PDF firmado aún no incluye la conformidad.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Reintentar/ })).toBeNull();
  });

  it('failed → el admin ve el detalle y Reintentar', () => {
    setup(
      {
        userId: 99, status: 'signed', signedAt: '2026-10-06T15:30:00Z',
        digitalSignature: pades, digitalSignatureStatus: 'failed', digitalSignatureError: 'TSA caída',
      },
      { admin: true, ownerId: 1 }
    );
    expect(screen.getByText('TSA caída')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Reintentar/ })).toBeInTheDocument();
  });

  it('failed sin metadata PAdES → tarjeta mínima; el admin ve el error y Reintentar', () => {
    setup(
      { userId: 99, digitalSignature: null, digitalSignatureStatus: 'failed', digitalSignatureError: 'Certificado vencido' },
      { admin: true, ownerId: 1 }
    );
    expect(screen.getByText('Firma de la empresa: error')).toBeInTheDocument();
    expect(screen.getByText('Certificado vencido')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Reintentar/ })).toBeInTheDocument();
  });

  it('failed sin metadata PAdES → el trabajador no ve detalle ni Reintentar', () => {
    setup({ digitalSignature: null, digitalSignatureStatus: 'failed', digitalSignatureError: null });
    expect(screen.getByText('Firma de la empresa: error')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Reintentar/ })).toBeNull();
  });

  it('sin firma digital → la vista de hoy (solo tarjeta de firma del trabajador)', () => {
    setup({});
    expect(screen.queryByText('Firmado por la empresa')).toBeNull();
    expect(screen.getByRole('button', { name: /Firmar documento/ })).toBeInTheDocument();
    expect(useDocumentsStore.getState().pollDigitalSignature).not.toHaveBeenCalled();
  });

  it('orden: conformidad antes que la firma de la empresa', () => {
    setup({ digitalSignature: pades, digitalSignatureStatus: 'signed' });
    const conformity = screen.getByTestId('conformity-card');
    const company = screen.getByTestId('company-signature-card');
    expect(conformity.compareDocumentPosition(company) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });

  it('para otros usuarios la tarjeta se titula "Conformidad del trabajador" sin botón Firmar', () => {
    setup(
      { userId: 99, digitalSignature: pades, digitalSignatureStatus: 'signed' },
      { admin: true, ownerId: 1 }
    );
    expect(screen.getByText('Conformidad del trabajador')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Firmar documento/ })).toBeNull();
  });

  it('"Ver detalles" muestra y oculta Cargo y TSA', () => {
    setup({
      digitalSignature: {
        ...pades,
        signer_details: { name: 'Ana Rep', organization: 'ACME SAC', ruc: '20123456789', title: 'Gerente', country: 'PE', locality: 'Lima' },
      },
      digitalSignatureStatus: 'signed',
    });
    // Resumen visible
    expect(screen.getByText('ACME SAC')).toBeInTheDocument();
    expect(screen.getByText('20123456789')).toBeInTheDocument();
    expect(screen.getByText('Ana Rep')).toBeInTheDocument();
    expect(screen.queryByText('Cargo')).toBeNull();
    expect(screen.queryByText(/Sello de tiempo/)).toBeNull();

    const toggle = screen.getByRole('button', { name: /Ver detalles/ });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    fireEvent.click(toggle);
    expect(screen.getByRole('button', { name: /Ocultar detalles/ })).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByText('Cargo')).toBeInTheDocument();
    expect(screen.getByText('Gerente')).toBeInTheDocument();
    expect(screen.getByText(/Sello de tiempo/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Ocultar detalles/ }));
    expect(screen.queryByText('Cargo')).toBeNull();
  });

  it('documento legado sin signer_details → Firmante (DN) dentro de "Ver detalles"', () => {
    setup({ digitalSignature: pades, digitalSignatureStatus: 'signed' });
    expect(screen.queryByText('Firmante')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: /Ver detalles/ }));
    expect(screen.getByText('Firmante')).toBeInTheDocument();
    expect(screen.getByText('CN=EMPRESA')).toBeInTheDocument();
  });

  it('la nota de "confiable" vive en un tooltip del ícono, no en un bloque fijo', async () => {
    setup({ digitalSignature: pades, digitalSignatureStatus: 'signed' });
    expect(screen.queryByText(/entidad certificadora acreditada/)).toBeNull();
    const trigger = screen.getByRole('button', { name: /confiabilidad/i });
    fireEvent.focus(trigger);
    expect((await screen.findAllByText(/entidad certificadora acreditada/)).length).toBeGreaterThan(0);
  });

  it('badge del tipo de certificado', () => {
    setup({ digitalSignature: pades, digitalSignatureStatus: 'signed' });
    expect(screen.getByText('Certificado de la empresa')).toBeInTheDocument();
  });

  it('la info del documento ya no repite "Conformidad" ni "Firma digital"', () => {
    setup({
      status: 'signed', signedAt: '2026-10-06T15:30:00Z',
      digitalSignature: pades, digitalSignatureStatus: 'signed',
    });
    const info = screen.getByTestId('document-info');
    expect(info).not.toHaveTextContent(/Conformidad/);
    expect(info).not.toHaveTextContent(/Firma digital/);
    expect(info).toHaveTextContent('Período');
  });
});
