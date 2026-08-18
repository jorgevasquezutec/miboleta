import { useEffect, useMemo, useState } from "react";
import { ShieldCheck, Save, Loader2, Lock, AlertTriangle } from "lucide-react";
import { useDocumentTitle } from "@/presentation/hooks";
import { Button } from "@/presentation/components/ui/button";
import { Switch } from "@/presentation/components/ui/switch";
import { Badge } from "@/presentation/components/ui/badge";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/presentation/components/ui/card";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { showApiError } from "@/presentation/utils/showApiError";
import { useAuditSettingsStore } from "@/presentation/stores";
import type { AuditActionSetting } from "@/core/domain/entities/AuditSettings";

// Etiquetas de categoría (alineadas con AuditLogsPage).
const CATEGORY_LABELS: Record<string, string> = {
  user: "Usuarios",
  document: "Documentos",
  vacation: "Vacaciones",
  tenant: "Organizaciones",
  batch: "Lotes de documentos",
  role: "Roles",
  platform: "Plataforma",
  signature: "Firma",
  user_batch: "Carga masiva",
  profile: "Perfil",
  audit: "Auditoría",
};

// Acciones de alto volumen: al desactivarlas se advierte del impacto probatorio.
const HIGH_VOLUME = ["document.viewed", "document.downloaded"];

export function AuditSettingsPage() {
  useDocumentTitle("Configuración de Auditoría");

  const { catalog, isLoading, isSaving, error, fetchCatalog, updateSettings, clearError } =
    useAuditSettingsStore();

  // Estado local: acción -> capturada (enabled). Se inicializa desde el catálogo.
  const [enabledMap, setEnabledMap] = useState<Record<string, boolean>>({});

  useEffect(() => {
    fetchCatalog();
  }, [fetchCatalog]);

  useEffect(() => {
    if (catalog.length) {
      setEnabledMap(Object.fromEntries(catalog.map((a) => [a.action, a.enabled])));
    }
  }, [catalog]);

  useEffect(() => {
    if (error) {
      toast.error(error);
      clearError();
    }
  }, [error, clearError]);

  // Agrupar por categoría, respetando el orden de aparición del backend.
  const grouped = useMemo(() => {
    const groups: Record<string, AuditActionSetting[]> = {};
    for (const item of catalog) {
      (groups[item.category] ??= []).push(item);
    }
    return groups;
  }, [catalog]);

  const toggle = (action: string, value: boolean) => {
    setEnabledMap((prev) => ({ ...prev, [action]: value }));
  };

  // ¿Hay algún de alto volumen que quedará desactivado?
  const highVolumeOff = catalog.some(
    (a) => HIGH_VOLUME.includes(a.action) && !a.locked && enabledMap[a.action] === false,
  );

  const dirty = catalog.some((a) => !a.locked && enabledMap[a.action] !== a.enabled);

  const handleSave = async () => {
    const disabledActions = catalog
      .filter((a) => !a.locked && enabledMap[a.action] === false)
      .map((a) => a.action);
    try {
      await updateSettings(disabledActions);
      toast.success("Configuración de auditoría actualizada exitosamente");
    } catch (error) {
      // A diferencia de fetchCatalog (que sigue pasando por `error` +
      // el useEffect de abajo), este catch tuesta directo con
      // showApiError para no perder mensajes si el backend algún día
      // devuelve más de uno.
      showApiError(error, "No se pudo guardar la configuración de auditoría");
    }
  };

  if (isLoading && !catalog.length) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#2563EB] mx-auto mb-4" />
          <p className="text-[#64748B]">Cargando configuración de auditoría...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
          <ShieldCheck className="w-6 h-6 text-[#2563EB]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold">Configuración de Auditoría</h1>
          <p className="text-[#64748B]">
            Activa o desactiva la captura de cada tipo de registro de auditoría
          </p>
        </div>
      </div>

      <Alert className="border-blue-200 bg-blue-50">
        <ShieldCheck className="w-4 h-4 text-blue-600" />
        <AlertDescription className="text-sm text-gray-700">
          Las acciones críticas de seguridad y legales (inicio de sesión, roles, contraseñas,
          cambios de empresa/plataforma, eliminaciones y firma) están{" "}
          <strong>siempre activas</strong> y no se pueden desactivar. Solo puedes desactivar las de
          telemetría o reconstruibles desde otras tablas.
        </AlertDescription>
      </Alert>

      {highVolumeOff && (
        <Alert className="border-amber-300 bg-amber-50">
          <AlertTriangle className="w-4 h-4 text-amber-600" />
          <AlertDescription className="text-sm text-gray-700">
            Estás por desactivar la captura de <strong>visualización/descarga de documentos</strong>.
            Estos registros son la evidencia de que el trabajador recibió/abrió su boleta (puesta a
            disposición). Desactivarlos reduce el valor probatorio ante un reclamo laboral.
          </AlertDescription>
        </Alert>
      )}

      {Object.entries(grouped).map(([category, actions]) => (
        <Card key={category}>
          <CardHeader>
            <CardTitle className="text-base">
              {CATEGORY_LABELS[category] ?? category}
            </CardTitle>
            <CardDescription>
              {actions.length} {actions.length === 1 ? "acción" : "acciones"}
            </CardDescription>
          </CardHeader>
          <CardContent className="divide-y">
            {actions.map((a) => (
              <div key={a.action} className="flex items-center justify-between gap-4 py-3">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-sm">{a.label}</span>
                    {a.locked && (
                      <Lock className="w-3.5 h-3.5 text-gray-400" aria-label="Siempre activa" />
                    )}
                  </div>
                  <div className="flex items-center gap-2 mt-0.5">
                    <code className="text-xs text-gray-400">{a.action}</code>
                    <Badge variant="secondary" className="text-xs">
                      {a.count.toLocaleString("es-PE")} registros
                    </Badge>
                  </div>
                </div>
                <Switch
                  checked={a.locked ? true : (enabledMap[a.action] ?? a.enabled)}
                  onCheckedChange={(v) => toggle(a.action, v)}
                  disabled={a.locked || isSaving}
                  aria-label={`Capturar ${a.label}`}
                />
              </div>
            ))}
          </CardContent>
        </Card>
      ))}

      {/* Guardar */}
      <div className="flex justify-end sticky bottom-0 bg-white/80 backdrop-blur py-4 border-t">
        <Button
          className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
          onClick={handleSave}
          disabled={isSaving || !dirty}
        >
          {isSaving ? (
            <>
              <Loader2 className="w-4 h-4 animate-spin" />
              Guardando...
            </>
          ) : (
            <>
              <Save className="w-4 h-4" />
              Guardar cambios
            </>
          )}
        </Button>
      </div>
    </div>
  );
}

export default AuditSettingsPage;
