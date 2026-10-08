import { useCallback, useEffect, useState } from 'react';
import { AlertTriangle, Loader2, ShieldCheck, Trash2, Upload } from 'lucide-react';
import { toast } from 'sonner';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/presentation/components/ui/card';
import { Badge } from '@/presentation/components/ui/badge';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Alert, AlertDescription } from '@/presentation/components/ui/alert';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/presentation/components/ui/tooltip';
import { ConfirmDialog } from '@/presentation/components/shared/ConfirmDialog';
import { formatDate } from '@/presentation/utils';
import { getErrorMessage } from '@/infrastructure/http/apiClient';
import { signatureSettingsRepository } from '@/infrastructure/persistence/repositories';
import { CertificatePreview, TenantCertificateStatus } from '@/core/domain/entities';
import { TenantCertificateUploadDialog } from './TenantCertificateUploadDialog';
import { getExpiryState } from './certificateExpiry';
import { TSA_HELP_TENANT, TSA_URL_ERROR, isValidTsaUrl } from './tsaUrl';
import { AMBER_BADGE, SourceBadge, WARNING_LABELS } from './certificateUi';

export const TENANT_CERTIFICATE_PASSWORD_REQUIRED = 'Ingresa la contraseña del certificado';

interface TenantCertificateSectionProps {
  /** Presente solo al editar una empresa existente (renovación). */
  tenantId?: string;
  /** RUC tipeado en el formulario (modo creación): contra él se compara el certificado. */
  tenantRuc?: string;
  // Modo creación: el estado vive en la página porque se sube tras crear la empresa.
  file?: File | null;
  password?: string;
  onFileChange?: (file: File | null) => void;
  onPasswordChange?: (password: string) => void;
  /** TSA propia opcional (modo creación); vacío = usa la TSA global. */
  tsaUrl?: string;
  onTsaUrlChange?: (tsaUrl: string) => void;
  /** Error de la página (p. ej. verificación fallida al enviar) mostrado en la sección. */
  error?: string | null;
  onErrorChange?: (error: string | null) => void;
}

const DESCRIPTION =
  'Certificado digital (.pfx / .p12) adquirido por la empresa a un proveedor acreditado (por ejemplo, Llama.pe). ' +
  'Tiene vigencia anual: cárgalo al crear la empresa y renuévalo cuando venza. ' +
  'Si la empresa no tiene certificado propio, sus documentos se firman con el certificado global de la plataforma.';

/**
 * Sección "Firma digital de la empresa" del formulario de empresa (solo root;
 * el padre decide si la monta). Creación: archivo + contraseña opcionales que la
 * página sube tras crear la empresa. Edición: estado + renovar/eliminar, acciones
 * independientes del submit del formulario.
 */
export function TenantCertificateSection(props: TenantCertificateSectionProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ShieldCheck className="h-5 w-5" />
          Firma digital de la empresa
        </CardTitle>
        <CardDescription>{DESCRIPTION}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {props.tenantId ? (
          <EditBody tenantId={Number(props.tenantId)} />
        ) : (
          <CreateBody {...props} />
        )}
      </CardContent>
    </Card>
  );
}

function PreviewResult({ preview, tenantRuc }: { preview: CertificatePreview; tenantRuc: string }) {
  const expiry = getExpiryState(preview.certificateExpiresAt);
  return (
    <div className="space-y-3">
      <dl className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div>
          <dt className="text-[#64748B]">RUC del certificado</dt>
          <dd className="font-medium">{preview.certificateRuc || '-'}</dd>
        </div>
        <div>
          <dt className="text-[#64748B]">Razón social</dt>
          <dd className="font-medium">{preview.certificateOrganization || '-'}</dd>
        </div>
        <div>
          <dt className="text-[#64748B]">Vence</dt>
          <dd
            className={
              expiry === 'expired'
                ? 'font-medium text-red-600'
                : expiry === 'expiring_soon'
                  ? 'font-medium text-amber-600'
                  : 'font-medium'
            }
          >
            {formatDate(preview.certificateExpiresAt)}
          </dd>
        </div>
      </dl>
      {preview.rucMismatch && (
        <Alert className="border-amber-300 bg-amber-50">
          <AlertTriangle className="w-4 h-4 text-amber-600" />
          <AlertDescription className="text-sm text-amber-900">
            El RUC del certificado ({preview.certificateRuc ?? '-'}) no coincide con el RUC de la empresa (
            {tenantRuc || '-'}). Se guardará igualmente y se mostrará un aviso.
          </AlertDescription>
        </Alert>
      )}
      {preview.warnings
        .filter((w) => w.code !== 'ruc_mismatch')
        .map((w) => (
          <Alert key={w.code} className="border-amber-300 bg-amber-50">
            <AlertTriangle className="w-4 h-4 text-amber-600" />
            <AlertDescription className="text-sm text-amber-900">{w.message}</AlertDescription>
          </Alert>
        ))}
    </div>
  );
}

function CreateBody({
  tenantRuc = '',
  file = null,
  password = '',
  onFileChange,
  onPasswordChange,
  tsaUrl = '',
  onTsaUrlChange,
  error,
  onErrorChange,
}: TenantCertificateSectionProps) {
  const [preview, setPreview] = useState<CertificatePreview | null>(null);
  // RUC del formulario con el que se calculó el preview: si cambia, el resultado queda obsoleto.
  const [verifiedRuc, setVerifiedRuc] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);

  const resetFeedback = () => {
    setPreview(null);
    onErrorChange?.(null);
  };

  const handleVerify = async () => {
    if (!file) return;
    if (!password) {
      onErrorChange?.(TENANT_CERTIFICATE_PASSWORD_REQUIRED);
      return;
    }
    setIsVerifying(true);
    onErrorChange?.(null);
    try {
      const result = await signatureSettingsRepository.previewCertificate({
        certificate: file,
        password,
        ruc: tenantRuc || undefined,
      });
      setVerifiedRuc(tenantRuc);
      setPreview(result);
    } catch (e) {
      setPreview(null);
      const message = getErrorMessage(e);
      onErrorChange?.(message);
      toast.error(message);
    } finally {
      setIsVerifying(false);
    }
  };

  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="tenant-cert-file">Archivo del certificado (.pfx / .p12)</Label>
          <Input
            id="tenant-cert-file"
            type="file"
            accept=".pfx,.p12"
            onChange={(e) => {
              resetFeedback();
              onFileChange?.(e.target.files?.[0] ?? null);
            }}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="tenant-cert-password">Contraseña del certificado</Label>
          <Input
            id="tenant-cert-password"
            type="password"
            value={password}
            autoComplete="new-password"
            onChange={(e) => {
              resetFeedback();
              onPasswordChange?.(e.target.value);
            }}
          />
        </div>
        <div className="space-y-2 sm:col-span-2">
          <Label htmlFor="tenant-cert-tsa">URL de sello de tiempo (TSA)</Label>
          <Input
            id="tenant-cert-tsa"
            type="url"
            placeholder="https://freetsa.org/tsr"
            value={tsaUrl}
            aria-invalid={!isValidTsaUrl(tsaUrl)}
            aria-describedby="tenant-cert-tsa-help"
            onChange={(e) => onTsaUrlChange?.(e.target.value)}
          />
          {isValidTsaUrl(tsaUrl) ? (
            <p id="tenant-cert-tsa-help" className="text-xs text-[#64748B]">
              {TSA_HELP_TENANT}
            </p>
          ) : (
            <p id="tenant-cert-tsa-help" className="text-xs text-red-600">
              {TSA_URL_ERROR}
            </p>
          )}
        </div>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {file && (
        <Button type="button" variant="outline" className="gap-2" onClick={handleVerify} disabled={isVerifying}>
          {isVerifying ? <Loader2 className="w-4 h-4 animate-spin" /> : <ShieldCheck className="w-4 h-4" />}
          Verificar certificado
        </Button>
      )}

      {preview && verifiedRuc === tenantRuc && (
        <PreviewResult preview={preview} tenantRuc={verifiedRuc} />
      )}
    </>
  );
}

function EditBody({ tenantId }: { tenantId: number }) {
  const [status, setStatus] = useState<TenantCertificateStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const load = useCallback(async () => {
    setIsLoading(true);
    setLoadError(null);
    try {
      setStatus(await signatureSettingsRepository.getTenantCertificate(tenantId));
    } catch (e) {
      setLoadError(getErrorMessage(e));
    } finally {
      setIsLoading(false);
    }
  }, [tenantId]);

  useEffect(() => {
    load();
  }, [load]);

  const handleDelete = async () => {
    setIsDeleting(true);
    try {
      const result = await signatureSettingsRepository.deleteTenantCertificate(tenantId);
      toast.success(result.message);
      await load();
    } catch (e) {
      toast.error(getErrorMessage(e));
    } finally {
      setIsDeleting(false);
    }
  };

  if (isLoading && !status) {
    return (
      <div className="text-sm text-[#64748B]">
        <Loader2 className="w-4 h-4 animate-spin inline mr-2" />
        Cargando certificado...
      </div>
    );
  }

  if (loadError && !status) {
    return (
      <Alert variant="destructive">
        <AlertDescription>{loadError}</AlertDescription>
      </Alert>
    );
  }

  if (!status) return null;

  const cert = status.certificate;
  const expiry = getExpiryState(cert?.certificateExpiresAt);

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        <SourceBadge source={status.certificateSource} />
        {status.warnings.map((warning) => (
          <Tooltip key={warning.code}>
            <TooltipTrigger asChild>
              <Badge className={AMBER_BADGE}>
                <AlertTriangle />
                {WARNING_LABELS[warning.code]}
              </Badge>
            </TooltipTrigger>
            <TooltipContent>{warning.message}</TooltipContent>
          </Tooltip>
        ))}
      </div>

      {status.hasOwnCertificate && cert && (
        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div>
            <dt className="text-[#64748B]">RUC del certificado</dt>
            <dd className="font-medium">{cert.certificateRuc || '-'}</dd>
          </div>
          <div>
            <dt className="text-[#64748B]">Razón social</dt>
            <dd className="font-medium">{cert.certificateOrganization || '-'}</dd>
          </div>
          <div>
            <dt className="text-[#64748B]">Vence</dt>
            <dd
              className={
                expiry === 'expired'
                  ? 'font-medium text-red-600'
                  : expiry === 'expiring_soon'
                    ? 'font-medium text-amber-600'
                    : 'font-medium'
              }
            >
              {formatDate(cert.certificateExpiresAt)}
            </dd>
          </div>
          <div>
            <dt className="text-[#64748B]">Cargado</dt>
            <dd className="font-medium">{formatDate(cert.uploadedAt)}</dd>
          </div>
        </dl>
      )}

      <div className="text-sm">
        <div className="text-[#64748B]">Sello de tiempo (TSA)</div>
        <div className="font-medium break-all">
          {status.hasOwnCertificate && cert?.tsaUrl ? cert.tsaUrl : 'Usa la TSA global'}
        </div>
      </div>

      <div className="flex gap-2">
        <Button
          type="button"
          variant="outline"
          className="gap-2"
          onClick={() => setUploadOpen(true)}
          disabled={isDeleting}
        >
          <Upload className="w-4 h-4" />
          {status.hasOwnCertificate ? 'Renovar certificado' : 'Cargar certificado'}
        </Button>
        {status.hasOwnCertificate && (
          <Button
            type="button"
            variant="outline"
            className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
            onClick={() => setDeleteOpen(true)}
            disabled={isDeleting}
            aria-label="Eliminar certificado"
            title="Eliminar certificado"
          >
            <Trash2 className="w-4 h-4" />
          </Button>
        )}
      </div>

      <TenantCertificateUploadDialog
        tenant={status}
        open={uploadOpen}
        onOpenChange={setUploadOpen}
        onUploaded={() => {
          load();
        }}
      />

      <ConfirmDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Eliminar certificado"
        description={`¿Eliminar el certificado de ${status.tenantName}? Sus documentos se firmarán con el certificado global.`}
        confirmText="Eliminar"
        variant="destructive"
        onConfirm={handleDelete}
      />
    </>
  );
}
