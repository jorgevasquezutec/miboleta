import { useEffect, useState } from "react";
import { Globe, Save, Loader2, Info } from "lucide-react";
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
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { usePlatformSettingsStore } from "@/presentation/stores";
import { formatDateTime } from "@/presentation/utils";

export function PlatformSettingsPage() {
  useDocumentTitle("IP Pública de la Plataforma");

  const { settings, isLoading, isSaving, error, fetchSettings, updateSettings, clearError } =
    usePlatformSettingsStore();

  const [publicIp, setPublicIp] = useState("");

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  useEffect(() => {
    if (settings) {
      setPublicIp(settings.publicIp ?? "");
    }
  }, [settings]);

  useEffect(() => {
    if (error) {
      toast.error(error);
      clearError();
    }
  }, [error, clearError]);

  const handleSave = async () => {
    try {
      await updateSettings({ publicIp: publicIp.trim() || null });
      toast.success("IP pública actualizada exitosamente");
    } catch {
      // El store ya guarda el error y el useEffect lo muestra en un toast
    }
  };

  if (isLoading && !settings) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#2563EB] mx-auto mb-4" />
          <p className="text-[#64748B]">Cargando configuración de la plataforma...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
          <Globe className="w-6 h-6 text-[#2563EB]" />
        </div>
        <div>
          <h1 className="text-xl font-semibold">IP Pública de la Plataforma</h1>
          <p className="text-[#64748B]">
            Registra la IP pública del servidor donde corre el servicio
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Configuración de Red</CardTitle>
          <CardDescription>
            Valor informativo usado para compartir con terceros (bancos, SUNAT, etc.) que requieran
            hacer whitelisting de la IP del servidor. No afecta el comportamiento de la plataforma.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Alert className="border-blue-200 bg-blue-50">
            <Info className="w-4 h-4 text-blue-600" />
            <AlertDescription className="text-sm text-gray-700">
              Este es un registro manual: la plataforma no detecta ni valida automáticamente la IP
              pública real del servidor. Actualízala aquí cada vez que cambie.
            </AlertDescription>
          </Alert>

          <div className="space-y-2 max-w-md">
            <Label htmlFor="public-ip">IP pública (IPv4 o IPv6)</Label>
            <Input
              id="public-ip"
              type="text"
              placeholder="200.10.20.30"
              value={publicIp}
              onChange={(e) => setPublicIp(e.target.value)}
              disabled={isSaving}
            />
          </div>

          <div className="space-y-1">
            <span className="text-sm text-[#64748B]">Última actualización</span>
            <p className="font-medium">
              {settings?.updatedAt ? formatDateTime(settings.updatedAt) : "-"}
            </p>
          </div>

          <Button
            className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
            onClick={handleSave}
            disabled={isSaving}
          >
            {isSaving ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Guardando...
              </>
            ) : (
              <>
                <Save className="w-4 h-4" />
                Guardar
              </>
            )}
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}

export default PlatformSettingsPage;
