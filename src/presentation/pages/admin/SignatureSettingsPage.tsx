import { useEffect, useState } from "react";
import {
  ShieldCheck,
  ShieldOff,
  Upload,
  Trash2,
  Loader2,
  FileKey,
  Info,
  AlertCircle,
} from "lucide-react";
import { useDocumentTitle } from "@/presentation/hooks";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import { Label } from "@/presentation/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/presentation/components/ui/card";
import { Separator } from "@/presentation/components/ui/separator";
import { Switch } from "@/presentation/components/ui/switch";
import { Badge } from "@/presentation/components/ui/badge";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/presentation/components/ui/alert-dialog";
import { toast } from "sonner";
import { useSignatureSettingsStore } from "@/presentation/stores";
import { formatDateTime } from "@/presentation/utils";

export function SignatureSettingsPage() {
  useDocumentTitle("Firma Digital");

  const { settings, isLoading, isSaving, error, fetchSettings, uploadCertificate, setEnabled, deleteCertificate, clearError } =
    useSignatureSettingsStore();

  const [certificateFile, setCertificateFile] = useState<File | null>(null);
  const [password, setPassword] = useState("");
  const [tsaUrl, setTsaUrl] = useState("");
  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  // Se incrementa tras cada carga exitosa para remontar el <input type="file">
  // nativo (Input no es un forwardRef, así que no podemos limpiarlo vía ref).
  const [fileInputKey, setFileInputKey] = useState(0);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  useEffect(() => {
    if (error) {
      toast.error(error);
      clearError();
    }
  }, [error, clearError]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setCertificateFile(file);
  };

  const resetForm = () => {
    setCertificateFile(null);
    setPassword("");
    setTsaUrl("");
    setFileInputKey((key) => key + 1);
  };

  const handleUpload = async () => {
    if (!certificateFile) {
      toast.error("Selecciona un archivo de certificado (.pfx o .p12)");
      return;
    }
    if (!password) {
      toast.error("Ingresa la contraseña del certificado");
      return;
    }

    try {
      await uploadCertificate({
        certificate: certificateFile,
        password,
        tsaUrl: tsaUrl.trim() || undefined,
      });
      toast.success("Certificado de firma cargado exitosamente");
      resetForm();
    } catch {
      // El store ya guarda el error y el useEffect lo muestra en un toast
    }
  };

  const handleToggleEnabled = async (checked: boolean) => {
    if (checked && !settings?.hasCertificate) {
      toast.error("No se puede activar la firma digital: no hay un certificado cargado");
      return;
    }

    try {
      await setEnabled(checked);
      toast.success(checked ? "Firma digital activada exitosamente" : "Firma digital desactivada exitosamente");
    } catch {
      // manejado por el toast de error general
    }
  };

  const handleDelete = async () => {
    try {
      await deleteCertificate();
      toast.success("Certificado de firma eliminado exitosamente");
      setShowDeleteDialog(false);
    } catch {
      setShowDeleteDialog(false);
    }
  };

  if (isLoading && !settings) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#2563EB] mx-auto mb-4" />
          <p className="text-[#64748B]">Cargando configuración de firma digital...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
          <FileKey className="w-6 h-6 text-[#2563EB]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold">Firma Digital</h1>
          <p className="text-[#64748B]">
            Configura el certificado de firma digital criptográfica (PAdES) de la plataforma
          </p>
        </div>
      </div>

      {/* Status Card */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Estado de la Firma Digital</CardTitle>
              <CardDescription>
                Al activarla, los documentos elegibles podrán firmarse criptográficamente con el certificado configurado
              </CardDescription>
            </div>
            <Badge
              className="text-white border-none gap-1"
              style={{ backgroundColor: settings?.signatureEnabled ? "#22c55e" : "#94a3b8" }}
            >
              {settings?.signatureEnabled ? (
                <>
                  <ShieldCheck className="w-3.5 h-3.5" /> Activada
                </>
              ) : (
                <>
                  <ShieldOff className="w-3.5 h-3.5" /> Desactivada
                </>
              )}
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="space-y-1">
              <h4 className="text-sm font-medium">Activar firma digital</h4>
              <p className="text-sm text-[#64748B]">
                {settings?.hasCertificate
                  ? "Habilita el uso del certificado configurado para firmar documentos"
                  : "Necesitas cargar un certificado antes de poder activarla"}
              </p>
            </div>
            <Switch
              checked={!!settings?.signatureEnabled}
              onCheckedChange={handleToggleEnabled}
              disabled={isSaving || (!settings?.hasCertificate && !settings?.signatureEnabled)}
            />
          </div>

          <Separator />

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Certificado cargado</span>
              <p className="font-medium">{settings?.hasCertificate ? "Sí" : "No"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Titular del certificado</span>
              <p className="font-medium">{settings?.certificateSubject || "-"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">URL de sello de tiempo (TSA)</span>
              <p className="font-medium break-all">{settings?.tsaUrl || "No configurada"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Fecha de carga</span>
              <p className="font-medium">
                {settings?.uploadedAt ? formatDateTime(settings.uploadedAt) : "-"}
              </p>
            </div>
          </div>

          {settings?.hasCertificate && (
            <>
              <Separator />
              <Button
                variant="outline"
                className="gap-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                onClick={() => setShowDeleteDialog(true)}
                disabled={isSaving}
              >
                <Trash2 className="w-4 h-4" />
                Eliminar Certificado
              </Button>
            </>
          )}
        </CardContent>
      </Card>

      {/* Upload Certificate Card */}
      <Card>
        <CardHeader>
          <CardTitle>{settings?.hasCertificate ? "Reemplazar Certificado" : "Cargar Certificado"}</CardTitle>
          <CardDescription>
            Sube el archivo .pfx o .p12 del certificado de firma digital de la plataforma (DS-009-2011-TR)
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Alert className="border-blue-200 bg-blue-50">
            <Info className="w-4 h-4 text-blue-600" />
            <AlertDescription className="text-sm text-gray-700">
              Este es el certificado ÚNICO de la plataforma: se usa para firmar todos los documentos elegibles,
              sin importar la empresa. Al subir uno nuevo, reemplaza al anterior.
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
            {certificateFile && (
              <p className="text-sm text-[#64748B]">Seleccionado: {certificateFile.name}</p>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
              <Label htmlFor="tsa-url">URL de sello de tiempo (TSA) - opcional</Label>
              <Input
                id="tsa-url"
                type="url"
                placeholder="https://freetsa.org/tsr"
                value={tsaUrl}
                onChange={(e) => setTsaUrl(e.target.value)}
                disabled={isSaving}
              />
            </div>
          </div>

          <Button
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
        </CardContent>
      </Card>

      {/* Delete Confirmation */}
      <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="flex items-center gap-2">
              <AlertCircle className="w-5 h-5 text-red-600" />
              ¿Eliminar certificado de firma?
            </AlertDialogTitle>
            <AlertDialogDescription>
              Esto eliminará el certificado configurado y desactivará automáticamente la firma digital.
              Los documentos ya firmados no se ven afectados, pero no podrás firmar nuevos documentos hasta
              cargar otro certificado.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isSaving}>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={isSaving}
              className="bg-red-600 hover:bg-red-700 text-white"
            >
              {isSaving ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Eliminando...
                </>
              ) : (
                <>
                  <Trash2 className="w-4 h-4 mr-2" />
                  Eliminar
                </>
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

export default SignatureSettingsPage;
