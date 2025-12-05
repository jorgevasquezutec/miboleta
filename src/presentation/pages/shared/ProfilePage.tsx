import { useState, useEffect } from "react";
import { ArrowLeft, User as UserIcon, Mail, Phone, FileText, Building2, Shield, Calendar, Save, Loader2 } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Input } from "@/presentation/components/ui/input";
import { Label } from "@/presentation/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "@/presentation/components/ui/avatar";
import { Badge } from "@/presentation/components/ui/badge";
import { toast } from "sonner";
import { useAuthStore } from "@/presentation/stores";

interface ProfilePageProps {
  onBack: () => void;
}

export function ProfilePage({ onBack }: ProfilePageProps) {
  const { user, me, updateProfile, isLoading } = useAuthStore();

  // Form state
  const [name, setName] = useState("");
  const [lastName, setLastName] = useState("");
  const [phone, setPhone] = useState("");
  const [isSaving, setIsSaving] = useState(false);

  // Cargar datos del usuario al montar el componente
  useEffect(() => {
    const loadUserData = async () => {
      try {
        await me(); // Llama a /api/me
      } catch (error) {
        toast.error("Error al cargar datos del perfil");
      }
    };

    loadUserData();
  }, [me]);

  // Actualizar form state cuando user cambia
  useEffect(() => {
    if (user) {
      setName(user.name || "");
      setLastName(user.last_name || "");
      setPhone(user.phone || "");
    }
  }, [user]);

  const handleSaveProfile = async () => {
    if (!name.trim()) {
      toast.error("El nombre es requerido");
      return;
    }

    setIsSaving(true);
    try {
      await updateProfile({
        name: name.trim(),
        last_name: lastName.trim(),
        phone: phone.trim(),
      });

      toast.success("Perfil actualizado exitosamente");
    } catch (error) {
      toast.error("Error al actualizar el perfil");
      console.error("Update profile error:", error);
    } finally {
      setIsSaving(false);
    }
  };

  const getRoleName = (role: string | null) => {
    switch (role) {
      case "root":
        return "Administrador Plataforma";
      case "admin":
        return "Administrador";
      case "client":
        return "Cliente";
      default:
        return "Usuario";
    }
  };

  const getRoleBadgeColor = (role: string | null) => {
    switch (role) {
      case "root":
        return "bg-purple-100 text-purple-800";
      case "admin":
        return "bg-blue-100 text-blue-800";
      case "client":
        return "bg-green-100 text-green-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

  if (isLoading && !user) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Loader2 className="w-8 h-8 animate-spin text-[#2563EB]" />
      </div>
    );
  }

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <p className="text-[#64748B]">No se pudo cargar la información del usuario</p>
      </div>
    );
  }

  const primaryTenant = user.primary_tenant;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" onClick={onBack}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div>
          <h1 className="text-2xl font-semibold text-[#1E293B]">Mi Perfil</h1>
          <p className="text-[#64748B]">
            Gestiona tu información personal y configuración de cuenta
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Profile Overview Card */}
        <Card className="lg:col-span-1 border-[rgba(0,0,0,0.1)]">
          <CardHeader>
            <CardTitle className="text-[#1E40AF]">Información General</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Avatar */}
            <div className="flex flex-col items-center gap-4">
              <Avatar className="w-24 h-24">
                <AvatarImage src="" />
                <AvatarFallback className="bg-[#2563EB] text-white text-2xl">
                  {(user.full_name || user.name || user.email || "U")
                    .split(" ")
                    .map((n) => n[0])
                    .join("")
                    .toUpperCase()
                    .slice(0, 2)}
                </AvatarFallback>
              </Avatar>
              <div className="text-center">
                <h3 className="font-semibold text-[#1E293B]">{user.full_name || user.name}</h3>
                <p className="text-sm text-[#64748B]">{user.email}</p>
              </div>
              <Badge className={getRoleBadgeColor(user.role)}>
                {getRoleName(user.role)}
              </Badge>
            </div>

            {/* Quick Info */}
            <div className="space-y-4 pt-4 border-t border-[rgba(0,0,0,0.1)]">
              {primaryTenant && (
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <Building2 className="w-5 h-5 text-[#2563EB]" />
                  </div>
                  <div>
                    <p className="text-sm text-[#64748B]">Empresa Principal</p>
                    <p className="font-medium text-[#1E293B]">{primaryTenant.name}</p>
                  </div>
                </div>
              )}

              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                  <Shield className="w-5 h-5 text-[#8B5CF6]" />
                </div>
                <div>
                  <p className="text-sm text-[#64748B]">Rol</p>
                  <p className="font-medium text-[#1E293B]">{getRoleName(user.role)}</p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                  <Calendar className="w-5 h-5 text-[#10B981]" />
                </div>
                <div>
                  <p className="text-sm text-[#64748B]">Estado</p>
                  <p className="font-medium text-[#1E293B]">
                    {user.status === "active" ? "Activo" : "Inactivo"}
                  </p>
                </div>
              </div>

              {user.tenants && user.tenants.length > 1 && (
                <div className="pt-4 border-t border-[rgba(0,0,0,0.1)]">
                  <p className="text-sm text-[#64748B] mb-2">Empresas asociadas</p>
                  <div className="space-y-2">
                    {user.tenants.map((tenant) => (
                      <div
                        key={tenant.id}
                        className="flex items-center justify-between p-2 bg-[#F1F5F9] rounded-lg"
                      >
                        <span className="text-sm text-[#1E293B]">{tenant.name}</span>
                        {tenant.is_primary && (
                          <Badge variant="secondary" className="text-xs">
                            Principal
                          </Badge>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Edit Profile Form */}
        <Card className="lg:col-span-2 border-[rgba(0,0,0,0.1)]">
          <CardHeader>
            <CardTitle className="text-[#1E40AF]">Editar Información Personal</CardTitle>
            <CardDescription>
              Actualiza tus datos personales y de contacto
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-2">
                <Label htmlFor="profile-name">
                  <div className="flex items-center gap-2 text-[#1E293B]">
                    <UserIcon className="w-4 h-4" />
                    Nombre *
                  </div>
                </Label>
                <Input
                  id="profile-name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Juan"
                  className="border-[rgba(0,0,0,0.1)]"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-lastname">
                  <div className="flex items-center gap-2 text-[#1E293B]">
                    <UserIcon className="w-4 h-4" />
                    Apellido
                  </div>
                </Label>
                <Input
                  id="profile-lastname"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  placeholder="Pérez"
                  className="border-[rgba(0,0,0,0.1)]"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-email">
                  <div className="flex items-center gap-2 text-[#1E293B]">
                    <Mail className="w-4 h-4" />
                    Correo Electrónico (Solo lectura)
                  </div>
                </Label>
                <Input
                  id="profile-email"
                  type="email"
                  value={user.email}
                  disabled
                  className="bg-gray-50 border-[rgba(0,0,0,0.1)]"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-phone">
                  <div className="flex items-center gap-2 text-[#1E293B]">
                    <Phone className="w-4 h-4" />
                    Teléfono
                  </div>
                </Label>
                <Input
                  id="profile-phone"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+51 999 999 999"
                  className="border-[rgba(0,0,0,0.1)]"
                />
              </div>

              {user.document_type && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="profile-doc-type">
                      <div className="flex items-center gap-2 text-[#1E293B]">
                        <FileText className="w-4 h-4" />
                        Tipo de Documento (Solo lectura)
                      </div>
                    </Label>
                    <Input
                      id="profile-doc-type"
                      value={user.document_type}
                      disabled
                      className="bg-gray-50 border-[rgba(0,0,0,0.1)]"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="profile-doc-number">
                      <div className="flex items-center gap-2 text-[#1E293B]">
                        <FileText className="w-4 h-4" />
                        Número de Documento (Solo lectura)
                      </div>
                    </Label>
                    <Input
                      id="profile-doc-number"
                      value={user.document_text || ""}
                      disabled
                      className="bg-gray-50 border-[rgba(0,0,0,0.1)]"
                    />
                  </div>
                </>
              )}
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-[rgba(0,0,0,0.1)]">
              <Button variant="outline" onClick={onBack}>
                Cancelar
              </Button>
              <Button
                className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
                onClick={handleSaveProfile}
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
                    Guardar Cambios
                  </>
                )}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Additional Info Card */}
      <Card className="border-[rgba(0,0,0,0.1)]">
        <CardHeader>
          <CardTitle className="text-[#1E40AF]">Información de la Cuenta</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <p className="text-sm text-[#64748B] mb-1">ID de Usuario</p>
              <p className="font-mono text-sm text-[#1E293B]">{user.id}</p>
            </div>
            <div>
              <p className="text-sm text-[#64748B] mb-1">Estado de Cuenta</p>
              <Badge className={user.status === "active" ? "bg-[#10B981] text-white" : "bg-[#EF4444] text-white"}>
                {user.status === "active" ? "Activa" : "Inactiva"}
              </Badge>
            </div>
            {primaryTenant && (
              <div>
                <p className="text-sm text-[#64748B] mb-1">RUC de Empresa</p>
                <p className="font-mono text-sm text-[#1E293B]">{primaryTenant.ruc}</p>
              </div>
            )}
          </div>

          {user.created_at && (
            <div className="mt-6 pt-6 border-t border-[rgba(0,0,0,0.1)]">
              <p className="text-sm text-[#64748B]">
                Cuenta creada el {new Date(user.created_at).toLocaleDateString("es-ES", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </p>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

export default ProfilePage;
