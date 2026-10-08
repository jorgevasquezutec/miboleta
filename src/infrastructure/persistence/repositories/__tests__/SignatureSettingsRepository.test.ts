import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@/infrastructure/http/apiClient', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/infrastructure/http/apiClient')>();
  return {
    ...actual,
    default: {
      get: vi.fn(),
      post: vi.fn(),
      put: vi.fn(),
      delete: vi.fn(),
    },
  };
});

import apiClient from '@/infrastructure/http/apiClient';
import { SignatureSettingsRepository } from '../SignatureSettingsRepository';

const tenantItem = {
  tenant_id: 5,
  tenant_name: 'Overhead Men',
  tenant_ruc: '20603839961',
  tenant_business_name: 'OVERHEAD MEN S.A.C.',
  tenant_status: 'active',
  certificate_source: 'tenant',
  has_own_certificate: true,
  certificate: {
    certificate_subject: 'BASILIO VENTURA WILLIAM RUC:20603839961',
    certificate_ruc: '20603839961',
    certificate_organization: 'OVERHEAD MEN S.A.C.',
    certificate_expires_at: '2028-09-21T15:51:00.000000Z',
    tsa_url: 'https://tsa.empresa.example/tsr',
    uploaded_at: '2026-10-06T15:00:00.000000Z',
    uploaded_by: { id: 1, name: 'William Basilio' },
  },
  ruc_mismatch: true,
  warnings: [{ code: 'ruc_mismatch', message: 'no coincide' }],
};

describe('SignatureSettingsRepository', () => {
  let repository: SignatureSettingsRepository;

  beforeEach(() => {
    vi.clearAllMocks();
    repository = new SignatureSettingsRepository();
  });

  it('mapea los campos nuevos del certificado global', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: {
          signature_enabled: true,
          has_certificate: true,
          certificate_subject: 'CN',
          certificate_ruc: '20603839961',
          certificate_organization: 'OVERHEAD MEN',
          certificate_expires_at: '2028-09-21T15:51:00.000000Z',
          tsa_url: null,
          uploaded_at: null,
        },
      },
    });

    const settings = await repository.getSettings();

    expect(settings.certificateRuc).toBe('20603839961');
    expect(settings.certificateOrganization).toBe('OVERHEAD MEN');
    expect(settings.certificateExpiresAt).toBe('2028-09-21T15:51:00.000000Z');
  });

  it('listTenantCertificates mapea snake_case a camelCase y envía only_warnings=1', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: [tenantItem],
        meta: { total: 1, current_page: 2, last_page: 3, per_page: 10, with_own_certificate: 1, with_warnings: 1, global_has_certificate: true },
      },
    });

    const result = await repository.listTenantCertificates({ search: 'over', onlyWarnings: true, page: 2, perPage: 25 });

    expect(apiClient.get).toHaveBeenCalledWith('/signature/tenants', {
      params: { search: 'over', only_warnings: 1, page: 2, per_page: 25 },
    });
    expect(result.summary).toEqual({
      total: 1,
      currentPage: 2,
      lastPage: 3,
      perPage: 10,
      withOwnCertificate: 1,
      withWarnings: 1,
      globalHasCertificate: true,
    });
    expect(result.items[0]).toMatchObject({
      tenantId: 5,
      tenantName: 'Overhead Men',
      certificateSource: 'tenant',
      hasOwnCertificate: true,
      rucMismatch: true,
    });
    expect(result.items[0].certificate?.certificateRuc).toBe('20603839961');
    expect(result.items[0].certificate?.uploadedBy).toEqual({ id: 1, name: 'William Basilio' });
  });

  it('listTenantCertificates omite params vacíos', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: [], meta: { total: 0, current_page: 1, last_page: 1, per_page: 10, with_own_certificate: 0, with_warnings: 0, global_has_certificate: false } },
    });

    await repository.listTenantCertificates();

    expect(apiClient.get).toHaveBeenCalledWith('/signature/tenants', {
      params: { search: undefined, only_warnings: undefined, page: undefined, per_page: undefined },
    });
  });

  it('uploadTenantCertificate envía multipart a /signature/tenants/5/certificate', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { message: 'ok', data: tenantItem } });
    const file = new File(['x'], 'cert.pfx');

    const result = await repository.uploadTenantCertificate(5, { certificate: file, password: 'secret' });

    const [url, body, config] = vi.mocked(apiClient.post).mock.calls[0];
    expect(url).toBe('/signature/tenants/5/certificate');
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get('password')).toBe('secret');
    expect((body as FormData).get('certificate')).toBeInstanceOf(File);
    expect(config).toEqual({ headers: { 'Content-Type': 'multipart/form-data' } });
    expect(result.message).toBe('ok');
    expect(result.item.tenantId).toBe(5);
    expect(result.item.certificate?.tsaUrl).toBe('https://tsa.empresa.example/tsr');
    // Sin TSA no se envía el campo.
    expect((body as FormData).has('tsa_url')).toBe(false);
  });

  it('uploadTenantCertificate envía tsa_url solo si no está vacío', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { message: 'ok', data: tenantItem } });
    const file = new File(['x'], 'cert.pfx');

    await repository.uploadTenantCertificate(5, { certificate: file, password: 'p', tsaUrl: ' https://freetsa.org/tsr ' });
    expect((vi.mocked(apiClient.post).mock.calls[0][1] as FormData).get('tsa_url')).toBe('https://freetsa.org/tsr');

    await repository.uploadTenantCertificate(5, { certificate: file, password: 'p', tsaUrl: '  ' });
    expect((vi.mocked(apiClient.post).mock.calls[1][1] as FormData).has('tsa_url')).toBe(false);
  });

  it('deleteTenantCertificate llama DELETE y mapea el resultado', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({
      data: {
        message: 'eliminado',
        data: { ...tenantItem, certificate_source: 'global', has_own_certificate: false, certificate: null, ruc_mismatch: false, warnings: [] },
      },
    });

    const result = await repository.deleteTenantCertificate(5);

    expect(apiClient.delete).toHaveBeenCalledWith('/signature/tenants/5/certificate');
    expect(result.item.certificateSource).toBe('global');
    expect(result.item.certificate).toBeNull();
  });

  it('envuelve los errores con toApiError', async () => {
    vi.mocked(apiClient.delete).mockRejectedValue({
      isAxiosError: true,
      message: 'fail',
      response: { status: 422, data: { message: 'La empresa no tiene certificado propio.' } },
    });

    await expect(repository.deleteTenantCertificate(5)).rejects.toMatchObject({
      message: 'La empresa no tiene certificado propio.',
    });
  });
  it('getTenantCertificate mapea snake_case a camelCase', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: tenantItem } });
    const result = await repository.getTenantCertificate(5);
    expect(apiClient.get).toHaveBeenCalledWith('/signature/tenants/5/certificate');
    expect(result.tenantId).toBe(5);
    expect(result.certificateSource).toBe('tenant');
    expect(result.certificate?.certificateRuc).toBe('20603839961');
    expect(result.rucMismatch).toBe(true);
  });

  it('previewCertificate envía multipart con ruc y mapea la respuesta', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: {
        data: {
          certificate_subject: 'CN',
          certificate_ruc: '20603839961',
          certificate_organization: 'ORG',
          certificate_expires_at: '2028-09-21T15:51:00.000000Z',
          ruc_mismatch: false,
          warnings: [],
        },
      },
    });
    const file = new File(['x'], 'firma.pfx');
    const result = await repository.previewCertificate({ certificate: file, password: 'pw', ruc: '20603839961' });
    const [url, body, config] = vi.mocked(apiClient.post).mock.calls[0];
    expect(url).toBe('/signature/certificate/preview');
    expect((body as FormData).get('password')).toBe('pw');
    expect((body as FormData).get('ruc')).toBe('20603839961');
    expect((body as FormData).get('certificate')).toBeInstanceOf(File);
    expect(config).toEqual({ headers: { 'Content-Type': 'multipart/form-data' } });
    expect(result).toMatchObject({ certificateRuc: '20603839961', rucMismatch: false, warnings: [] });
  });

  it('previewCertificate sin ruc no lo agrega al FormData', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { data: { certificate_subject: null, certificate_ruc: null, certificate_organization: null, certificate_expires_at: null, ruc_mismatch: false, warnings: [] } },
    });
    await repository.previewCertificate({ certificate: new File(['x'], 'a.pfx'), password: 'pw' });
    const body = vi.mocked(apiClient.post).mock.calls[0][1] as FormData;
    expect(body.has('ruc')).toBe(false);
  });
});
