import { useState } from "react";
import {
  ArrowLeft,
  UserPlus,
  Search,
  MoreVertical,
  Mail,
  Shield,
  Trash2,
  Edit,
} from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "../ui/card";
import { toast } from "sonner";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../ui/table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "../ui/dialog";
import { Label } from "../ui/label";
import { Avatar, AvatarFallback, AvatarImage } from "../ui/avatar";
import { Badge } from "../ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "../ui/dropdown-menu";

interface UserManagementViewProps {
  onBack: () => void;
  tenantId?: string; // if provided, manage users for this tenant only
}

import { usePlatform } from "../../contexts/PlatformContext";

export function UserManagementView({ onBack, tenantId }: UserManagementViewProps) {
  const [searchTerm, setSearchTerm] = useState("");
  const [isAddUserOpen, setIsAddUserOpen] = useState(false);
  const [selectedTenantId, setSelectedTenantId] = useState<string>("");
  const { users, createUser, tenants } = usePlatform();

  const isPlatformAdmin = !tenantId; // If no tenantId is provided, user is platform admin

  const filteredUsers = users
    .filter((user) => (tenantId ? user.tenantId === tenantId : true))
    .filter(
      (user) =>
        user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.email.toLowerCase().includes(searchTerm.toLowerCase())
    );

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <div>
            <h1>Gestión de Usuarios</h1>
            <p className="text-[#64748B]">
              Administra los usuarios y sus permisos en la plataforma
            </p>
          </div>
        </div>
        <Dialog open={isAddUserOpen} onOpenChange={setIsAddUserOpen}>
          <DialogTrigger asChild>
            <Button className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]">
              <UserPlus className="w-4 h-4" />
              Agregar Usuario
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Agregar Nuevo Usuario</DialogTitle>
              <DialogDescription>
                Completa los datos del nuevo usuario. Se enviará un email de
                bienvenida con instrucciones de acceso.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="name">Nombre Completo</Label>
                <Input id="name" placeholder="Ej: Juan Pérez" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="email">Correo Electrónico</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="juan.perez@empresa.com"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="department">Departamento</Label>
                <input id="department" className="w-full h-10 px-3 border rounded" placeholder="Ej: Ventas" />
              </div>

              {/* Show tenant selector only for platform admin */}
              {isPlatformAdmin && (
                <div className="space-y-2">
                  <Label htmlFor="tenant-select">Empresa</Label>
                  <select
                    id="tenant-select"
                    title="Seleccionar empresa"
                    className="w-full h-10 px-3 border rounded"
                    value={selectedTenantId}
                    onChange={(e) => setSelectedTenantId(e.target.value)}
                  >
                    <option value="">Seleccionar empresa...</option>
                    {tenants.map((tenant) => (
                      <option key={tenant.id} value={tenant.id}>
                        {tenant.name} ({tenant.ruc})
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div className="space-y-2">
                <Label htmlFor="role">Rol</Label>
                <select id="role-select" title="Seleccionar rol" className="w-full h-10 px-3 border rounded">
                  <option value="employee">Empleado</option>
                  <option value="manager">Gerente</option>
                  <option value="admin">Administrador</option>
                </select>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsAddUserOpen(false)}>
                Cancelar
              </Button>
              <Button
                className="bg-[#2563EB] hover:bg-[#1E40AF]"
                onClick={() => {
                  const nameInput = document.getElementById("name") as HTMLInputElement | null;
                  const emailInput = document.getElementById("email") as HTMLInputElement | null;
                  const departmentInput = document.getElementById("department") as HTMLInputElement | null;
                  const roleSelect = document.getElementById("role-select") as HTMLSelectElement | null;

                  const name = nameInput?.value || "";
                  const email = emailInput?.value || "";
                  const department = departmentInput?.value || undefined;
                  const sel = roleSelect?.value || "employee";

                  // Validation
                  if (!name || !email) {
                    toast.error("Por favor completa el nombre y el email");
                    return;
                  }

                  // Determine tenant ID
                  let finalTenantId: string | undefined;
                  if (isPlatformAdmin) {
                    // Platform admin must select a tenant
                    if (!selectedTenantId) {
                      toast.error("Por favor selecciona una empresa");
                      return;
                    }
                    finalTenantId = selectedTenantId;
                  } else {
                    // Tenant admin creates users in their own tenant
                    finalTenantId = tenantId;
                  }

                  // Map role based on selection and context
                  let role: "employee" | "manager" | "tenant-admin" | "platform-admin";
                  if (sel === "admin") {
                    // Admin role - if platform admin creating, make tenant-admin
                    role = isPlatformAdmin ? "tenant-admin" : "tenant-admin";
                  } else if (sel === "manager") {
                    role = "manager";
                  } else {
                    role = "employee";
                  }

                  createUser({
                    name,
                    email,
                    role,
                    tenantId: finalTenantId,
                    status: "active",
                    department
                  } as any);

                  toast.success("Usuario creado exitosamente!");
                  setIsAddUserOpen(false);
                  setSelectedTenantId(""); // Reset selection

                  // Clear form fields
                  if (nameInput) nameInput.value = "";
                  if (emailInput) emailInput.value = "";
                  if (departmentInput) departmentInput.value = "";
                  if (roleSelect) roleSelect.value = "employee";
                }}
              >
                Crear Usuario
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Total Usuarios</p>
                <h2>{tenantId ? filteredUsers.length : users.length}</h2>
              </div>
              <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Shield className="w-5 h-5 text-[#2563EB]" />
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Activos</p>
                <h2>{(tenantId ? filteredUsers : users).filter((u) => u.status === "active").length}</h2>
              </div>
              <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                <Shield className="w-5 h-5 text-[#10B981]" />
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Administradores</p>
                <h2>
                  {(tenantId ? filteredUsers : users).filter((u) => u.role === "tenant-admin" || u.role === "platform-admin").length}
                </h2>
              </div>
              <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <Shield className="w-5 h-5 text-[#8B5CF6]" />
              </div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-[#64748B] mb-1">Inactivos</p>
                <h2>
                  {(tenantId ? filteredUsers : users).filter((u) => u.status === "inactive").length}
                </h2>
              </div>
              <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <Shield className="w-5 h-5 text-[#64748B]" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* User List */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Lista de Usuarios</CardTitle>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" />
              <Input
                placeholder="Buscar usuarios..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Usuario</TableHead>
                <TableHead>Departamento</TableHead>
                <TableHead>Rol</TableHead>
                <TableHead>Documentos</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredUsers.map((user) => (
                <TableRow key={user.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Avatar>
                        <AvatarImage src="" />
                        <AvatarFallback className="bg-[#2563EB] text-white">
                          {user.name
                            .split(" ")
                            .map((n) => n[0])
                            .join("")
                            .toUpperCase()
                            .slice(0, 2)}
                        </AvatarFallback>
                      </Avatar>
                      <div>
                        <p>{user.name}</p>
                        <p className="text-[#64748B]">{user.email}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>{(user as any).department || "-"}</TableCell>
                  <TableCell>
                    <Badge
                      variant="secondary"
                      className={
                        user.role === "platform-admin" || user.role === "tenant-admin"
                          ? "bg-purple-100 text-purple-800"
                          : user.role === "manager"
                          ? "bg-blue-100 text-blue-800"
                          : "bg-gray-100 text-gray-800"
                      }
                    >
                      {user.role === "platform-admin"
                        ? "Admin Plataforma"
                        : user.role === "tenant-admin"
                        ? "Administrador"
                        : user.role === "manager"
                        ? "Gerente"
                        : "Empleado"}
                    </Badge>
                  </TableCell>
                  <TableCell>{(user as any).documents ?? 0}</TableCell>
                  <TableCell>
                    {user.status === "active" ? (
                      <Badge className="bg-[#10B981] text-white">Activo</Badge>
                    ) : (
                      <Badge variant="secondary">Inactivo</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon">
                          <MoreVertical className="w-4 h-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem>
                          <Edit className="w-4 h-4 mr-2" />
                          Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                          <Mail className="w-4 h-4 mr-2" />
                          Enviar Email
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                          <Shield className="w-4 h-4 mr-2" />
                          Cambiar Rol
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-[#EF4444]">
                          <Trash2 className="w-4 h-4 mr-2" />
                          Eliminar
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
