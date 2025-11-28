import { useState, useEffect } from "react";
import { ArrowLeft, Building2, Palette, Save, Upload } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import { Label } from "@/presentation/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Separator } from "@/presentation/components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/presentation/components/ui/tabs";
import { Switch } from "@/presentation/components/ui/switch";
import { toast } from "sonner";

interface CompanySettingsViewProps {
  onBack: () => void;
}

export function CompanySettingsView({ onBack }: CompanySettingsViewProps) {
  // Mock tenant data - replace with real store when ready
  const mockTenant = {
    name: "Mi Empresa",
    ruc: "20123456789",
    address: "Av. Principal 123",
    phone: "+51 999 999 999",
    email: "contacto@miempresa.com",
    branding: {
      primaryColor: "#2563EB",
      secondaryColor: "#7C3AED",
      logoUrl: ""
    }
  };
  
  const [primaryColor, setPrimaryColor] = useState(mockTenant.branding.primaryColor);
  const [secondaryColor, setSecondaryColor] = useState(mockTenant.branding.secondaryColor);
  const [companyName, setCompanyName] = useState(mockTenant.name);
  const [companyRuc, setCompanyRuc] = useState(mockTenant.ruc);
  const [companyAddress, setCompanyAddress] = useState(mockTenant.address);
  const [companyPhone, setCompanyPhone] = useState(mockTenant.phone);
  const [companyEmail, setCompanyEmail] = useState(mockTenant.email);
  const [emailNotifications, setEmailNotifications] = useState(true);
  const [autoReminders, setAutoReminders] = useState(true);
  const handleSave = () => {
    // TODO: Implement tenant settings update with use cases
    console.log("Saving settings:", {
      primaryColor,
      secondaryColor,
      companyName,
      companyRuc,
      companyAddress,
      companyPhone,
      companyEmail
    });
    
    toast.success("Configuración guardada exitosamente", {
      description: "Los cambios se han aplicado a toda la plataforma",
    });
  };
  
  const handleLogoUpload = () => {
    // TODO: Implement logo upload with use case
    toast.success("Logo cargado exitosamente");
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div>
            <h1>Configuración de Empresa</h1>
            <p className="text-[#64748B]">
              Personaliza la apariencia y comportamiento de la plataforma
            </p>
          </div>
        </div>
        <Button
          className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
          onClick={handleSave}
        >
          <Save className="w-4 h-4" />
          Guardar Cambios
        </Button>
      </div>

      {/* Settings Tabs */}
      <Tabs defaultValue="branding" className="space-y-6">
        <TabsList>
          <TabsTrigger value="branding">Marca y Diseño</TabsTrigger>
          <TabsTrigger value="general">General</TabsTrigger>
          <TabsTrigger value="notifications">Notificaciones</TabsTrigger>
          <TabsTrigger value="security">Seguridad</TabsTrigger>
        </TabsList>

        {/* Branding Tab */}
        <TabsContent value="branding" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Logo de la Empresa</CardTitle>
              <CardDescription>
                Sube el logo que aparecerá en toda la plataforma
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center gap-6">
                <div className="w-32 h-32 border-2 border-dashed border-[rgba(0,0,0,0.1)] rounded-lg flex items-center justify-center bg-[#F1F5F9] overflow-hidden">
                  <Building2 className="w-16 h-16 text-[#64748B]" />
                </div>
                <div className="space-y-3">
                  <Button variant="outline" className="gap-2" onClick={handleLogoUpload}>
                    <Upload className="w-4 h-4" />
                    Subir Logo
                  </Button>
                  <p className="text-[#64748B]">
                    Formato PNG o SVG. Tamaño recomendado: 400x400px
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Colores de Marca</CardTitle>
              <CardDescription>
                Personaliza los colores principales de la plataforma
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-3">
                  <Label htmlFor="primary-color">Color Primario</Label>
                  <div className="flex gap-3">
                    <Input
                      id="primary-color"
                      type="color"
                      value={primaryColor}
                      onChange={(e) => setPrimaryColor(e.target.value)}
                      className="w-20 h-11 p-1"
                    />
                    <Input
                      value={primaryColor}
                      onChange={(e) => setPrimaryColor(e.target.value)}
                      className="flex-1"
                    />
                  </div>
                  <p className="text-[#64748B]">
                    Se usa en botones principales, enlaces y elementos destacados
                  </p>
                </div>

                <div className="space-y-3">
                  <Label htmlFor="secondary-color">Color Secundario</Label>
                  <div className="flex gap-3">
                    <Input
                      id="secondary-color"
                      type="color"
                      value={secondaryColor}
                      onChange={(e) => setSecondaryColor(e.target.value)}
                      className="w-20 h-11 p-1"
                    />
                    <Input
                      value={secondaryColor}
                      onChange={(e) => setSecondaryColor(e.target.value)}
                      className="flex-1"
                    />
                  </div>
                  <p className="text-[#64748B]">
                    Se usa en textos y elementos secundarios
                  </p>
                </div>
              </div>

              <Separator />

              {/* Preview */}
              <div>
                <h4 className="mb-4">Vista Previa</h4>
                <div className="border border-[rgba(0,0,0,0.1)] rounded-lg p-6 space-y-4">
                  <div className="flex gap-3">
                    <Button
                      className="text-white"
                      style={{ backgroundColor: primaryColor }}
                    >
                      Botón Primario
                    </Button>
                    <Button
                      variant="outline"
                      style={{ borderColor: primaryColor, color: primaryColor }}
                    >
                      Botón Secundario
                    </Button>
                  </div>
                  <p style={{ color: secondaryColor }}>
                    Ejemplo de texto con color secundario
                  </p>
                  <div
                    className="p-4 rounded-lg"
                    style={{ backgroundColor: `${primaryColor}10` }}
                  >
                    <p style={{ color: primaryColor }}>
                      Card con fondo de color primario
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* General Tab */}
        <TabsContent value="general" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Información de la Empresa</CardTitle>
              <CardDescription>
                Datos generales que aparecerán en la plataforma
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="company-name">Nombre de la Empresa</Label>
                  <Input
                    id="company-name"
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-ruc">RUC / Tax ID</Label>
                  <Input 
                    id="company-ruc" 
                    value={companyRuc}
                    onChange={(e) => setCompanyRuc(e.target.value)}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="company-address">Dirección</Label>
                <Input
                  id="company-address"
                  value={companyAddress}
                  onChange={(e) => setCompanyAddress(e.target.value)}
                />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="company-phone">Teléfono</Label>
                  <Input 
                    id="company-phone" 
                    value={companyPhone}
                    onChange={(e) => setCompanyPhone(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="company-email">Email de Contacto</Label>
                  <Input
                    id="company-email"
                    type="email"
                    value={companyEmail}
                    onChange={(e) => setCompanyEmail(e.target.value)}
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Notifications Tab */}
        <TabsContent value="notifications" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Configuración de Notificaciones</CardTitle>
              <CardDescription>
                Controla cómo y cuándo se envían las notificaciones
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <h4>Notificaciones por Email</h4>
                  <p className="text-[#64748B]">
                    Enviar emails cuando se carguen o firmen documentos
                  </p>
                </div>
                <Switch
                  checked={emailNotifications}
                  onCheckedChange={setEmailNotifications}
                />
              </div>

              <Separator />

              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <h4>Recordatorios Automáticos</h4>
                  <p className="text-[#64748B]">
                    Enviar recordatorios para documentos pendientes de firma
                  </p>
                </div>
                <Switch
                  checked={autoReminders}
                  onCheckedChange={setAutoReminders}
                />
              </div>

              <Separator />

              <div className="space-y-3">
                <h4>Frecuencia de Recordatorios</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="reminder-days">Días antes del vencimiento</Label>
                    <Input
                      id="reminder-days"
                      type="number"
                      defaultValue="3"
                      min="1"
                      max="30"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="reminder-frequency">
                      Frecuencia (días)
                    </Label>
                    <Input
                      id="reminder-frequency"
                      type="number"
                      defaultValue="2"
                      min="1"
                      max="7"
                    />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Security Tab */}
        <TabsContent value="security" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Configuración de Seguridad</CardTitle>
              <CardDescription>
                Ajusta las medidas de seguridad para tu organización
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <h4>Autenticación de Dos Factores</h4>
                  <p className="text-[#64748B]">
                    Requerir 2FA para todos los administradores
                  </p>
                </div>
                <Switch defaultChecked />
              </div>

              <Separator />

              <div className="flex items-center justify-between">
                <div className="space-y-1">
                  <h4>Geolocalización en Firmas</h4>
                  <p className="text-[#64748B]">
                    Incluir ubicación en los certificados de firma
                  </p>
                </div>
                <Switch defaultChecked />
              </div>

              <Separator />

              <div className="space-y-3">
                <h4>Política de Contraseñas</h4>
                <div className="space-y-2">
                  <Label htmlFor="password-length">
                    Longitud mínima de contraseña
                  </Label>
                  <Input
                    id="password-length"
                    type="number"
                    defaultValue="8"
                    min="6"
                    max="32"
                  />
                </div>
              </div>

              <Separator />

              <div className="space-y-3">
                <h4>Sesiones</h4>
                <div className="space-y-2">
                  <Label htmlFor="session-timeout">
                    Tiempo de inactividad (minutos)
                  </Label>
                  <Input
                    id="session-timeout"
                    type="number"
                    defaultValue="30"
                    min="5"
                    max="120"
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export default CompanySettingsView;
