import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/infrastructure/http/apiClient', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/infrastructure/http/apiClient')>();
  return {
    ...actual,
    default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  };
});

import apiClient from '@/infrastructure/http/apiClient';
import { DocumentRepository } from '../DocumentRepository';

const pades = {
  method: 'pades_pyhanko',
  signer_subject: 'CN=EMPRESA',
  includes_conformity: true,
  conformity_signed_at: '2026-10-06T15:30:00-05:00',
  first_signed_at: '2026-10-06T10:00:00-05:00',
  resign_count: 1,
};

const rawDoc = {
  id: 1,
  tenant_id: 1,
  status: 'signed',
  requires_signature: true,
  signature: { verification_method: 'email_2fa', user_name: 'Jorge', pdf_mark: 'pades', document_sha256: 'abc' },
  signed_at: '2026-10-06T15:30:00-05:00',
  digital_signature: pades,
  digitally_signed_at: '2026-10-06T15:31:00-05:00',
  digital_signature_status: 'signed',
  digital_signature_error: null,
};

describe('DocumentRepository (firma digital separada)', () => {
  let repo: DocumentRepository;

  beforeEach(() => {
    vi.clearAllMocks();
    repo = new DocumentRepository();
  });

  it('mapea los campos digital_* de DocumentResource', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: rawDoc } });
    const doc = await repo.findById(1);

    expect(doc.digitalSignature).toEqual(pades);
    expect(doc.digitallySignedAt).toBe('2026-10-06T15:31:00-05:00');
    expect(doc.digitalSignatureStatus).toBe('signed');
    expect(doc.digitalSignatureError).toBeNull();
    expect(doc.signature?.pdf_mark).toBe('pades');
    expect(doc.signedAt).toBe('2026-10-06T15:30:00-05:00');
  });

  it('documentos sin firma digital quedan en null', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: { ...rawDoc, status: 'pending', signature: null, signed_at: null, digital_signature: undefined, digitally_signed_at: undefined, digital_signature_status: undefined } },
    });
    const doc = await repo.findById(1);
    expect(doc.signature).toBeNull();
    expect(doc.digitalSignature).toBeNull();
    expect(doc.digitalSignatureStatus).toBeNull();
    expect(doc.digitallySignedAt).toBeNull();
  });

  it('getSignatureStatus mapea el estado digital', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        document_id: 1, requires_signature: true, is_signed: true, signed_at: 'x', signature: {},
        digital_signature_status: 'pending', digitally_signed_at: null, includes_conformity: false,
      },
    });
    const res = await repo.getSignatureStatus(1);
    expect(res.digitalSignatureStatus).toBe('pending');
    expect(res.digitallySignedAt).toBeNull();
    expect(res.includesConformity).toBe(false);
  });

  it('signDocument mapea digital_signature_status', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { message: 'ok', signed_at: 'x', digital_signature_status: 'pending', document: {} },
    });
    const res = await repo.signDocument(1, '123456');
    expect(res.digitalSignatureStatus).toBe('pending');
  });

  it('verifySignature mapea includes_conformity', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: { verifiable: true, intact: true, valid: true, includes_conformity: true } },
    });
    const res = await repo.verifySignature(1);
    expect(res.includesConformity).toBe(true);
  });

  it('findAll envía digital_status', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } },
    });
    await repo.findAll({ digitalStatus: 'failed' });
    expect(vi.mocked(apiClient.get).mock.calls[0][0]).toContain('digital_status=failed');
  });

  it('signDigital hace POST a sign-digital', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });
    await repo.signDigital(7);
    expect(apiClient.post).toHaveBeenCalledWith('/documents/7/sign-digital');
  });
});
