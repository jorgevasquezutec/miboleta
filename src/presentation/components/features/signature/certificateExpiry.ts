// Estado de vencimiento de un certificado (mismo umbral que el backend:
// TenantSignatureCertificate::EXPIRING_SOON_DAYS).
export const EXPIRING_SOON_DAYS = 30;

export type ExpiryState = 'expired' | 'expiring_soon' | 'ok' | 'unknown';

export function getExpiryState(expiresAt: string | null | undefined, now: Date = new Date()): ExpiryState {
  if (!expiresAt) return 'unknown';
  const expires = new Date(expiresAt).getTime();
  if (Number.isNaN(expires)) return 'unknown';
  const diff = expires - now.getTime();
  if (diff <= 0) return 'expired';
  if (diff <= EXPIRING_SOON_DAYS * 24 * 60 * 60 * 1000) return 'expiring_soon';
  return 'ok';
}
