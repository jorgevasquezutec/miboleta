import { useState } from 'react';
import { Loader2, Upload, Info } from 'lucide-react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/presentation/components/ui/dialog';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Alert, AlertDescription } from '@/presentation/components/ui/alert';
import { useSignatureSettingsStore } from '@/presentation/stores';
import { TSA_HELP_GLOBAL } from './tsaUrl';

interface GlobalCertificateUploadFormProps {
  onUploaded?: () => void;
  /** Si se pasa (dentro del diálogo) se muestra el botón Cancelar. */
  onCancel?: () => void;
}

/** Formulario de carga del certificado global (inline o dentro del diálogo). */
export function GlobalCertificateUploadForm({ onUploaded, onCancel }: GlobalCertificateUploadFormProps) {
  const isSaving = useSignatureSettingsStore((s) => s.isSaving);
  const uploadCertificate = useSignatureSettingsStore((s) => s.uploadCertificate);

  const [certificateFile, setCertificateFile] = useState<File | null>(null);
  const [password, setPassword] = useState('');
  const [tsaUrl, setTsaUrl] = useState('');
  // Se incrementa tras cada carga exitosa para remontar el <input type="file">
  // nativo (Input no es un forwardRef, así que no podemos limpiarlo vía ref).
  const [fileInputKey, setFileInputKey] = useState(0);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setCertificateFile(e.target.files?.[0] ?? null);
  };

  const resetForm = () => {
    setCertificateFile(null);
    setPassword('');
    setTsaUrl('');
    setFileInputKey((key) => key + 1);
  };

  const handleUpload = async () => {
    if (!certificateFile) {
      toast.error('Selecciona un archivo de certificado (.pfx o .p12)');
      return;
    }
    if (!password) {
      toast.error('Ingresa la contraseña del certificado');
      return;
    }

    try {
      await uploadCertificate({
        certificate: certificateFile,
        password,
        tsaUrl: tsaUrl.trim() || undefined,
      });
      toast.success('Certificado de firma cargado exitosamente');
      resetForm();
      onUploaded?.();
    } catch {
      // El store ya guarda el error y la página lo muestra en un toast
    }
  };

  return (
    <div className="space-y-4">
      <Alert className="border-blue-200 bg-blue-50">
        <Info className="w-4 h-4 text-blue-600" />
        <AlertDescription className="text-sm text-gray-700">
          Se usa para firmar los documentos de las empresas que no tienen certificado propio.
          Al subir uno nuevo, reemplaza al anterior.
        </AlertDescription>
      </Alert>

      <div className="space-y-2">
        <Label htmlFor="certificate-file">Archivo del certificado (.pfx / .p12)</Label>
        <Input
          id="certificate-file"
          key={fileInputKey}
          type="file"
          accept=".pfx,.p12"
          onChange={handleFileChange}
          disabled={isSaving}
        />
        {certificateFile && <p className="text-sm text-[#64748B]">Seleccionado: {certificateFile.name}</p>}
      </div>

      <div className="space-y-2">
        <Label htmlFor="certificate-password">Contraseña del certificado</Label>
        <Input
          id="certificate-password"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          disabled={isSaving}
          autoComplete="new-password"
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="tsa-url">URL de sello de tiempo (TSA)</Label>
        <Input
          id="tsa-url"
          type="url"
          placeholder="https://freetsa.org/tsr"
          value={tsaUrl}
          onChange={(e) => setTsaUrl(e.target.value)}
          disabled={isSaving}
          aria-describedby="tsa-url-help"
        />
        <p id="tsa-url-help" className="text-xs text-[#64748B]">
          {TSA_HELP_GLOBAL}
        </p>
      </div>

      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel} disabled={isSaving}>
            Cancelar
          </Button>
        )}
        <Button
          type="button"
          className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
          onClick={handleUpload}
          disabled={isSaving || !certificateFile || !password}
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
      </div>
    </div>
  );
}

interface GlobalCertificateUploadDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Diálogo para renovar/reemplazar el certificado global. Se cierra al cargar con éxito. */
export function GlobalCertificateUploadDialog({ open, onOpenChange }: GlobalCertificateUploadDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Renovar / Reemplazar certificado</DialogTitle>
          <DialogDescription>
            Sube el archivo .pfx o .p12 del certificado global de firma digital de la plataforma (DS-009-2011-TR)
          </DialogDescription>
        </DialogHeader>
        <GlobalCertificateUploadForm onUploaded={() => onOpenChange(false)} onCancel={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  );
}
