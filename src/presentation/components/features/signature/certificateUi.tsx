import { Badge } from '@/presentation/components/ui/badge';
import { CertificateWarningCode, TenantCertificateStatus } from '@/core/domain/entities';

// Etiquetas/badges compartidos entre TenantCertificatesCard y TenantCertificateSection.
export const WARNING_LABELS: Record<CertificateWarningCode, string> = {
  ruc_mismatch: 'RUC no coincide',
  ruc_not_found: 'RUC no legible',
  expired: 'Vencido',
  expiring_soon: 'Por vencer',
  no_certificate: 'Sin certificado',
};

export const AMBER_BADGE = 'bg-amber-100 text-amber-800 border-amber-300 gap-1';

export function SourceBadge({ source }: { source: TenantCertificateStatus['certificateSource'] }) {
  if (source === 'tenant') {
    return <Badge className="text-white border-none" style={{ backgroundColor: '#22c55e' }}>Propio</Badge>;
  }
  if (source === 'global') {
    return <Badge className="text-white border-none" style={{ backgroundColor: '#94a3b8' }}>Global (por defecto)</Badge>;
  }
  return <Badge className="text-white border-none" style={{ backgroundColor: '#ef4444' }}>Sin certificado</Badge>;
}
