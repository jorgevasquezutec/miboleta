import { useState } from "react";
import { ArrowLeft, User, Mail, Building2, Shield, Calendar, Save } from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "../ui/avatar";
import { Badge } from "../ui/badge";
import { toast } from "sonner";
import { usePlatform } from "../../contexts/PlatformContext";

interface UserProfileViewProps {
  onBack: () => void;
  currentUser: {
    name: string;
    email: string;
    role: "platform-admin" | "tenant-admin" | "manager" | "employee" | null;
    tenantId?: string;
  };
}

export function UserProfileView({ onBack, currentUser }: UserProfileViewProps) {
  const { users, updateUser, tenants } = usePlatform();

  // Find full user data
  const fullUser = users.find((u) => u.email === currentUser.email);
  const userTenant = tenants.find((t) => t.id === currentUser.tenantId);

  const [name, setName] = useState(currentUser.name);
  const [email, setEmail] = useState(currentUser.email);
  const [department, setDepartment] = useState((fullUser as any)?.department || "");

  const handleSaveProfile = () => {
    if (!name || !email) {
      toast.error("Por favor completa todos los campos requeridos");
      return;
    }

    if (fullUser) {
      updateUser(fullUser.id, {
        name,
        email,
        ...(department && { department: department as any }),
      });
      toast.success("Perfil actualizado exitosamente");
    }
  };

  const getRoleName = (role: string | null) => {
    switch (role) {
      case "platform-admin":
        return "Administrador de Plataforma";
      case "tenant-admin":
        return "Administrador de Empresa";
      case "manager":
        return "Gerente";
      case "employee":
        return "Empleado";
      default:
        return "Usuario";
    }
  };

  const getRoleBadgeColor = (role: string | null) => {
    switch (role) {
      case "platform-admin":
      case "tenant-admin":
        return "bg-purple-100 text-purple-800";
      case "manager":
        return "bg-blue-100 text-blue-800";
      default:
        return "bg-gray-100 text-gray-800";
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" onClick={onBack}>
          <ArrowLeft className="w-5 h-5" />
        </Button>
        <div>
          <h1>Mi Perfil</h1>
          <p className="text-[#64748B]">
            Gestiona tu información personal y configuración de cuenta
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Profile Overview Card */}
        <Card className="lg:col-span-1">
          <CardHeader>
            <CardTitle>Información General</CardTitle>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Avatar */}
            <div className="flex flex-col items-center gap-4">
              <Avatar className="w-24 h-24">
                <AvatarImage src="" />
                <AvatarFallback className="bg-[#2563EB] text-white text-2xl">
                  {currentUser.name
                    .split(" ")
                    .map((n) => n[0])
                    .join("")
                    .toUpperCase()
                    .slice(0, 2)}
                </AvatarFallback>
              </Avatar>
              <div className="text-center">
                <h3>{currentUser.name}</h3>
                <p className="text-[#64748B]">{currentUser.email}</p>
              </div>
              <Badge variant="secondary" className={getRoleBadgeColor(currentUser.role)}>
                {getRoleName(currentUser.role)}
              </Badge>
            </div>

            {/* Quick Info */}
            <div className="space-y-4 pt-4 border-t">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                  <Building2 className="w-5 h-5 text-[#2563EB]" />
                </div>
                <div>
                  <p className="text-sm text-[#64748B]">Empresa</p>
                  <p className="font-medium">{userTenant?.name || "N/A"}</p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                  <Shield className="w-5 h-5 text-[#8B5CF6]" />
                </div>
                <div>
                  <p className="text-sm text-[#64748B]">Rol</p>
                  <p className="font-medium">{getRoleName(currentUser.role)}</p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                  <Calendar className="w-5 h-5 text-[#10B981]" />
                </div>
                <div>
                  <p className="text-sm text-[#64748B]">Estado</p>
                  <p className="font-medium">Activo</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Edit Profile Form */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Editar Información Personal</CardTitle>
            <CardDescription>
              Actualiza tus datos personales y de contacto
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-2">
                <Label htmlFor="profile-name">
                  <div className="flex items-center gap-2">
                    <User className="w-4 h-4" />
                    Nombre Completo
                  </div>
                </Label>
                <Input
                  id="profile-name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Juan Pérez"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-email">
                  <div className="flex items-center gap-2">
                    <Mail className="w-4 h-4" />
                    Correo Electrónico
                  </div>
                </Label>
                <Input
                  id="profile-email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="juan.perez@empresa.com"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-department">Departamento</Label>
                <Input
                  id="profile-department"
                  value={department}
                  onChange={(e) => setDepartment(e.target.value)}
                  placeholder="Ej: Ventas, Recursos Humanos"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="profile-role">Rol (Solo lectura)</Label>
                <Input
                  id="profile-role"
                  value={getRoleName(currentUser.role)}
                  disabled
                  className="bg-gray-50"
                />
              </div>

              {userTenant && (
                <div className="space-y-2">
                  <Label htmlFor="profile-company">Empresa (Solo lectura)</Label>
                  <Input
                    id="profile-company"
                    value={userTenant.name}
                    disabled
                    className="bg-gray-50"
                  />
                </div>
              )}
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t">
              <Button variant="outline" onClick={onBack}>
                Cancelar
              </Button>
              <Button
                className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]"
                onClick={handleSaveProfile}
              >
                <Save className="w-4 h-4" />
                Guardar Cambios
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Additional Info Card */}
      <Card>
        <CardHeader>
          <CardTitle>Información de la Cuenta</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <p className="text-sm text-[#64748B] mb-1">ID de Usuario</p>
              <p className="font-mono text-sm">{fullUser?.id || "N/A"}</p>
            </div>
            <div>
              <p className="text-sm text-[#64748B] mb-1">Estado de Cuenta</p>
              <Badge className="bg-[#10B981] text-white">
                {fullUser?.status === "active" ? "Activa" : "Inactiva"}
              </Badge>
            </div>
            {userTenant && (
              <div>
                <p className="text-sm text-[#64748B] mb-1">RUC de Empresa</p>
                <p className="font-mono text-sm">{userTenant.ruc}</p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
