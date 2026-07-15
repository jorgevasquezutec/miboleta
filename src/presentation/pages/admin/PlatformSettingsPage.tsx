import { useEffect, useState } from "react";
import { Globe, Save, Loader2, Info, Mail } from "lucide-react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/presentation/components/ui/select";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { usePlatformSettingsStore } from "@/presentation/stores";
import type { MailEncryption } from "@/core/domain/entities/Tenant";
import { formatDateTime } from "@/presentation/utils";

export function PlatformSettingsPage() {
  useDocumentTitle("Configuración de la Plataforma");

  const { settings, isLoading, isSaving, error, fetchSettings, updateSettings, clearError } =
    usePlatformSettingsStore();

  const [publicIp, setPublicIp] = useState("");

  // SMTP global. mail_port se maneja como string en el input y se convierte a
  // número al armar el payload; mail_encryption usa el centinela 'none'.
  const [mailForm, setMailForm] = useState({
    mailHost: "",
    mailPort: "",
    mailUsername: "",
    mailEncryption: "none" as "none" | MailEncryption,
    mailFromAddress: "",
    mailFromName: "",
  });

  // La contraseña SMTP se maneja aparte: nunca se precarga (el backend no la
  // devuelve) y solo se envía cuando el usuario escribe una nueva.
  const [mailPassword, setMailPassword] = useState("");

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  useEffect(() => {
    if (settings) {
      setPublicIp(settings.publicIp ?? "");
      setMailForm({
        mailHost: settings.mailHost ?? "",
        mailPort: settings.mailPort != null ? String(settings.mailPort) : "",
        mailUsername: settings.mailUsername ?? "",
        mailEncryption: settings.mailEncryption ?? "none",
        mailFromAddress: settings.mailFromAddress ?? "",
        mailFromName: settings.mailFromName ?? "",
      });
      // La contraseña nunca se precarga (write-only en el backend).
      setMailPassword("");
    }
  }, [settings]);

  useEffect(() => {
    if (error) {
      toast.error(error);
      clearError();
    }
  }, [error, clearError]);

  const setMailField = (field: keyof typeof mailForm, value: string) => {
    setMailForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    try {
      const trimmed = (v: string) => v.trim();
      await updateSettings({
        publicIp: publicIp.trim() || null,
        mailHost: trimmed(mailForm.mailHost) || null,
        mailPort: mailForm.mailPort ? Number(mailForm.mailPort) : null,
        mailUsername: trimmed(mailForm.mailUsername) || null,
        mailEncryption: mailForm.mailEncryption === "none" ? null : mailForm.mailEncryption,
        mailFromAddress: trimmed(mailForm.mailFromAddress) || null,
        mailFromName: trimmed(mailForm.mailFromName) || null,
        // Solo se incluye si el usuario escribió una nueva (write-only).
        ...(mailPassword.trim() !== "" ? { mailPassword } : {}),
      });
      toast.success("Configuración de la plataforma actualizada exitosamente");
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
          <h1 className="text-xl font-semibold">Configuración de la Plataforma</h1>
          <p className="text-[#64748B]">
            IP pública del servidor y servidor de correo por defecto
          </p>
        </div>
      </div>

      {/* IP pública */}
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
        </CardContent>
      </Card>

      {/* SMTP global */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5 text-blue-600" />
            Servidor de correo (SMTP global)
          </CardTitle>
          <CardDescription>
            Opcional. Mailer por defecto de la plataforma. Si una empresa no tiene su propio
            servidor configurado, se usará este; si este tampoco está configurado, se usa el
            servidor definido en el archivo <code>.env</code> del servidor.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Host */}
            <div className="space-y-2">
              <Label htmlFor="mail_host">Host</Label>
              <Input
                id="mail_host"
                value={mailForm.mailHost}
                onChange={(e) => setMailField("mailHost", e.target.value)}
                placeholder="Ej: smtp.gmail.com"
                disabled={isSaving}
              />
            </div>

            {/* Port */}
            <div className="space-y-2">
              <Label htmlFor="mail_port">Puerto</Label>
              <Input
                id="mail_port"
                type="number"
                min={1}
                max={65535}
                value={mailForm.mailPort}
                onChange={(e) => setMailField("mailPort", e.target.value)}
                placeholder="Ej: 587"
                disabled={isSaving}
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Username */}
            <div className="space-y-2">
              <Label htmlFor="mail_username">Usuario</Label>
              <Input
                id="mail_username"
                value={mailForm.mailUsername}
                onChange={(e) => setMailField("mailUsername", e.target.value)}
                placeholder="Ej: no-reply@plataforma.com"
                autoComplete="off"
                disabled={isSaving}
              />
            </div>

            {/* Password */}
            <div className="space-y-2">
              <Label htmlFor="mail_password">
                {settings?.hasMailPassword ? "Cambiar contraseña" : "Contraseña"}
              </Label>
              <Input
                id="mail_password"
                type="password"
                value={mailPassword}
                onChange={(e) => setMailPassword(e.target.value)}
                placeholder={
                  settings?.hasMailPassword
                    ? "•••••• (sin cambios)"
                    : "Contraseña del servidor SMTP"
                }
                autoComplete="new-password"
                disabled={isSaving}
              />
              {settings?.hasMailPassword && (
                <p className="text-xs text-gray-500">
                  Déjalo vacío para mantener la contraseña actual.
                </p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Encryption */}
            <div className="space-y-2">
              <Label htmlFor="mail_encryption">Cifrado</Label>
              <Select
                value={mailForm.mailEncryption}
                onValueChange={(value: "none" | MailEncryption) =>
                  setMailField("mailEncryption", value)
                }
                disabled={isSaving}
              >
                <SelectTrigger id="mail_encryption">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Ninguno</SelectItem>
                  <SelectItem value="tls">TLS</SelectItem>
                  <SelectItem value="ssl">SSL</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* From Address */}
            <div className="space-y-2">
              <Label htmlFor="mail_from_address">Correo remitente</Label>
              <Input
                id="mail_from_address"
                type="email"
                value={mailForm.mailFromAddress}
                onChange={(e) => setMailField("mailFromAddress", e.target.value)}
                placeholder="Ej: no-reply@plataforma.com"
                disabled={isSaving}
              />
            </div>

            {/* From Name */}
            <div className="space-y-2">
              <Label htmlFor="mail_from_name">Nombre remitente</Label>
              <Input
                id="mail_from_name"
                value={mailForm.mailFromName}
                onChange={(e) => setMailField("mailFromName", e.target.value)}
                placeholder="Ej: Mi Boleta"
                disabled={isSaving}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Última actualización + Guardar */}
      <div className="flex items-center justify-between gap-4">
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
      </div>
    </div>
  );
}

export default PlatformSettingsPage;
