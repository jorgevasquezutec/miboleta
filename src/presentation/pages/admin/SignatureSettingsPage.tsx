import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "react-router-dom";
import {
  ShieldCheck,
  ShieldOff,
  Trash2,
  Loader2,
  FileKey,
  AlertCircle,
  AlertTriangle,
  RefreshCw,
  Upload,
} from "lucide-react";
import { useDocumentTitle } from "@/presentation/hooks";
import { Button } from "@/presentation/components/ui/button";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/presentation/components/ui/tabs";
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
import { formatDate, formatDateTime } from "@/presentation/utils";
import {
  TenantCertificatesCard,
  GlobalCertificateUploadDialog,
  GlobalCertificateUploadForm,
  getExpiryState,
} from "@/presentation/components/features/signature";

type TabValue = "empresas" | "global";

export function SignatureSettingsPage() {
  useDocumentTitle("Firma Digital");

  const { settings, isLoading, isSaving, error, fetchSettings, setEnabled, deleteCertificate, clearError, fetchTenantCertificates, tenantSummary } =
    useSignatureSettingsStore();

  const [showDeleteDialog, setShowDeleteDialog] = useState(false);
  const [showUploadDialog, setShowUploadDialog] = useState(false);

  // Pestaña en la URL con push (navegación normal): así "atrás" vuelve a la pestaña anterior.
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTab: TabValue = searchParams.get("tab") === "global" ? "global" : "empresas";
  // Radix notifica el mismo valor dos veces (click + foco) y el router aplica
  // la navegación de forma asíncrona, así que activeTab aún no cambió en la
  // segunda llamada: sin este ref se apilan dos entradas iguales y "atrás"
  // no cambia de pestaña.
  const requestedTab = useRef<TabValue>(activeTab);
  useEffect(() => {
    requestedTab.current = activeTab;
  }, [activeTab]);
  const setActiveTab = (value: string) => {
    if (value === requestedTab.current) return;
    requestedTab.current = value === "global" ? "global" : "empresas";
    const next = new URLSearchParams(searchParams);
    if (value === "global") next.set("tab", "global");
    else next.delete("tab");
    setSearchParams(next);
  };

  useEffect(() => {
    fetchSettings();
    fetchTenantCertificates();
  }, [fetchSettings, fetchTenantCertificates]);

  useEffect(() => {
    if (error) {
      toast.error(error);
      clearError();
    }
  }, [error, clearError]);

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

  const expiryState = getExpiryState(settings?.certificateExpiresAt);

  const hasCertificate = !!settings?.hasCertificate;
  const warnings = tenantSummary?.withWarnings ?? 0;
  const expiryColor =
    expiryState === "expired" ? "text-red-600" : expiryState === "expiring_soon" ? "text-amber-700" : "text-[#64748B]";

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
        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
          <FileKey className="w-6 h-6 text-[#2563EB]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold">Firma Digital</h1>
          <p className="text-[#64748B]">
            Configura el certificado global y los certificados por empresa de la firma digital criptográfica (PAdES)
          </p>
        </div>
      </div>

      {/* Franja de estado (siempre visible) */}
      <div
        data-testid="signature-status-strip"
        className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border bg-white px-4 py-2.5 text-sm"
      >
        <span
          className={`inline-flex items-center gap-1.5 font-medium ${
            settings?.signatureEnabled ? "text-green-600" : "text-[#64748B]"
          }`}
        >
          {settings?.signatureEnabled ? <ShieldCheck className="w-4 h-4" /> : <ShieldOff className="w-4 h-4" />}
          {settings?.signatureEnabled ? "Firma digital activada" : "Firma digital desactivada"}
        </span>
        {hasCertificate ? (
          <span className={expiryColor}>
            Certificado global vence {formatDate(settings?.certificateExpiresAt ?? null)}
          </span>
        ) : (
          <>
            <span className="inline-flex items-start gap-1.5 text-amber-700 min-w-0">
              <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>
                No hay certificado global. Es necesario para activar la firma y para las empresas sin certificado propio.
              </span>
            </span>
            <Button size="sm" variant="outline" className="gap-1.5" onClick={() => setActiveTab("global")}>
              <Upload className="w-3.5 h-3.5" />
              Cargar certificado global
            </Button>
          </>
        )}
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="grid w-full grid-cols-2 sm:inline-flex sm:w-auto">
          <TabsTrigger value="empresas" className="gap-2">
            Empresas
            {warnings > 0 && (
              <span
                aria-label={`${warnings} ${warnings === 1 ? "empresa con aviso" : "empresas con aviso"}`}
                className="inline-flex items-center gap-0.5 rounded-full border border-amber-300 bg-amber-100 px-1.5 text-xs font-medium text-amber-800"
              >
                <AlertTriangle className="w-3 h-3" aria-hidden="true" />
                {warnings}
              </span>
            )}
          </TabsTrigger>
          <TabsTrigger value="global">Certificado global</TabsTrigger>
        </TabsList>

        <TabsContent value="empresas" className="space-y-6">
          <TenantCertificatesCard />
        </TabsContent>

        <TabsContent value="global" className="space-y-6">
      {/* Certificado global */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Certificado global de la plataforma</CardTitle>
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
                  ? "La firma digital se activa para toda la plataforma; requiere el certificado global."
                  : "Necesitas cargar el certificado global antes de poder activarla"}
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
              <p className="font-medium flex items-center gap-2">
                {settings?.hasCertificate ? "Sí" : "No"}
                {expiryState === "expired" && (
                  <Badge className="text-white border-none" style={{ backgroundColor: "#ef4444" }}>
                    Vencido
                  </Badge>
                )}
                {expiryState === "expiring_soon" && (
                  <Badge className="bg-amber-100 text-amber-800 border-amber-300">Por vencer</Badge>
                )}
              </p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Titular del certificado</span>
              <p className="font-medium">{settings?.certificateSubject || "-"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">RUC del certificado</span>
              <p className="font-medium">{settings?.certificateRuc || "-"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Razón social (certificado)</span>
              <p className="font-medium">{settings?.certificateOrganization || "-"}</p>
            </div>
            <div className="space-y-1">
              <span className="text-sm text-[#64748B]">Vence</span>
              <p className="font-medium">{formatDate(settings?.certificateExpiresAt ?? null)}</p>
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
              <div className="flex flex-wrap gap-2">
              <Button
                variant="outline"
                className="gap-2"
                onClick={() => setShowUploadDialog(true)}
                disabled={isSaving}
              >
                <RefreshCw className="w-4 h-4" />
                Renovar / Reemplazar certificado
              </Button>
              <Button
                variant="outline"
                className="gap-2 text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                onClick={() => setShowDeleteDialog(true)}
                disabled={isSaving}
              >
                <Trash2 className="w-4 h-4" />
                Eliminar Certificado
              </Button>
              </div>
            </>
          )}
        </CardContent>
      </Card>

          {!hasCertificate && (
            <Card>
              <CardHeader>
                <CardTitle>Cargar Certificado</CardTitle>
                <CardDescription>
                  Sube el archivo .pfx o .p12 del certificado global de firma digital de la plataforma (DS-009-2011-TR)
                </CardDescription>
              </CardHeader>
              <CardContent>
                <GlobalCertificateUploadForm />
              </CardContent>
            </Card>
          )}
        </TabsContent>
      </Tabs>

      <GlobalCertificateUploadDialog open={showUploadDialog} onOpenChange={setShowUploadDialog} />

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
