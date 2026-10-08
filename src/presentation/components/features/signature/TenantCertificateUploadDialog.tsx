import { useState } from 'react';
import { Loader2, Upload, Info } from 'lucide-react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/presentation/components/ui/dialog';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Alert, AlertDescription } from '@/presentation/components/ui/alert';
import { useSignatureSettingsStore } from '@/presentation/stores';
import { getErrorMessage } from '@/infrastructure/http/apiClient';
import { TenantCertificateStatus } from '@/core/domain/entities';
import { TenantCertificateMutationResult } from '@/core/domain/repositories/ISignatureSettingsRepository';
import { TSA_HELP_TENANT, TSA_URL_ERROR, isValidTsaUrl } from './tsaUrl';

interface TenantCertificateUploadDialogProps {
  tenant: TenantCertificateStatus | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUploaded?: (result: TenantCertificateMutationResult) => void;
}

/**
 * Diálogo para cargar/reemplazar el certificado de firma de UNA empresa.
 * Si el RUC del certificado no coincide con el de la empresa el backend igual
 * lo guarda (201) y devuelve rucMismatch: aquí solo se avisa.
 */
export function TenantCertificateUploadDialog({
  tenant,
  open,
  onOpenChange,
  onUploaded,
}: TenantCertificateUploadDialogProps) {
  const uploadTenantCertificate = useSignatureSettingsStore((s) => s.uploadTenantCertificate);
  const savingTenantId = useSignatureSettingsStore((s) => s.savingTenantId);

  const [file, setFile] = useState<File | null>(null);
  const [password, setPassword] = useState('');
  // null = sin editar: se muestra la TSA actual de la empresa (al renovar).
  const [tsaEdit, setTsaEdit] = useState<string | null>(null);
  // Se incrementa para remontar el <input type="file"> nativo y limpiarlo.
  const [fileInputKey, setFileInputKey] = useState(0);

  const tsaUrl = tsaEdit ?? tenant?.certificate?.tsaUrl ?? '';
  const tsaInvalid = !isValidTsaUrl(tsaUrl);
  const isSaving = !!tenant && savingTenantId === tenant.tenantId;

  const resetForm = () => {
    setFile(null);
    setPassword('');
    setTsaEdit(null);
    setFileInputKey((key) => key + 1);
  };

  const handleOpenChange = (next: boolean) => {
    if (isSaving) return;
    if (!next) resetForm();
    onOpenChange(next);
  };

  const handleSubmit = async () => {
    if (!tenant) return;
    if (!file) {
      toast.error('Selecciona un archivo .pfx o .p12');
      return;
    }
    if (!password) {
      toast.error('Ingresa la contraseña del certificado');
      return;
    }
    if (tsaInvalid) {
      toast.error(TSA_URL_ERROR);
      return;
    }

    try {
      const result = await uploadTenantCertificate(tenant.tenantId, {
        certificate: file,
        password,
        tsaUrl: tsaUrl.trim() || undefined,
      });
      if (result.item.rucMismatch) {
        toast.warning(result.message);
      } else {
        toast.success(result.message);
      }
      resetForm();
      onOpenChange(false);
      onUploaded?.(result);
    } catch (error) {
      // Único toast de error; el diálogo sigue abierto y conserva la contraseña
      toast.error(getErrorMessage(error));
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Certificado de {tenant?.tenantName}</DialogTitle>
          <DialogDescription>
            RUC de la empresa: {tenant?.tenantRuc || '-'}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <Alert className="border-blue-200 bg-blue-50">
            <Info className="w-4 h-4 text-blue-600" />
            <AlertDescription className="text-sm text-gray-700">
              Si el RUC del certificado no coincide con el de la empresa, el certificado se guardará
              igualmente y se mostrará un aviso.
            </AlertDescription>
          </Alert>

          <div className="space-y-2">
            <Label htmlFor="tenant-certificate-file">Archivo del certificado (.pfx / .p12)</Label>
            <Input
              id="tenant-certificate-file"
              key={fileInputKey}
              type="file"
              accept=".pfx,.p12"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="tenant-certificate-password">Contraseña del certificado</Label>
            <Input
              id="tenant-certificate-password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              disabled={isSaving}
              autoComplete="new-password"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="tenant-certificate-tsa">URL de sello de tiempo (TSA)</Label>
            <Input
              id="tenant-certificate-tsa"
              type="url"
              placeholder="https://freetsa.org/tsr"
              value={tsaUrl}
              onChange={(e) => setTsaEdit(e.target.value)}
              disabled={isSaving}
              aria-invalid={tsaInvalid}
              aria-describedby="tenant-certificate-tsa-help"
            />
            {tsaInvalid ? (
              <p id="tenant-certificate-tsa-help" className="text-xs text-red-600">
                {TSA_URL_ERROR}
              </p>
            ) : (
              <p id="tenant-certificate-tsa-help" className="text-xs text-[#64748B]">
                {TSA_HELP_TENANT}
              </p>
            )}
          </div>
        </div>

        <DialogFooter className="gap-2">
          <Button type="button" variant="outline" onClick={() => handleOpenChange(false)} disabled={isSaving}>
            Cancelar
          </Button>
          <Button
            type="button"
            className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
            onClick={handleSubmit}
            disabled={isSaving || !file || !password || tsaInvalid}
          >
            {isSaving ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Subiendo...
              </>
            ) : (
              <>
                <Upload className="w-4 h-4" />
                Cargar Certificado
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
