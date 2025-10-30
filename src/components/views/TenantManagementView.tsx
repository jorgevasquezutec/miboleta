import { useState } from "react";
import { ArrowLeft, Building2, Plus, Edit, Trash2, CheckCircle2, XCircle, Users } from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { Badge } from "../ui/badge";
import { StatsCard } from "../StatsCard";
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
import { toast } from "sonner";

interface TenantManagementViewProps {
  onBack: () => void;
}

import { usePlatform } from "../../contexts/PlatformContext";

export function TenantManagementView({ onBack }: TenantManagementViewProps) {
  const { tenants, createTenant, updateTenant, deleteTenant, users } = usePlatform();

  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [newTenantName, setNewTenantName] = useState("");
  const [newTenantRuc, setNewTenantRuc] = useState("");
  const [editingTenant, setEditingTenant] = useState<{
    id: string;
    name: string;
    ruc: string;
    primaryColor: string;
  } | null>(null);

  // Helper function to count users per tenant
  const getUsersCount = (tenantId: string) => {
    return users.filter((u) => u.tenantId === tenantId).length;
  };

  const handleCreateTenant = () => {
    if (!newTenantName || !newTenantRuc) {
      toast.error("Por favor completa todos los campos");
      return;
    }

    createTenant({ name: newTenantName, ruc: newTenantRuc, status: "active", primaryColor: "#2563EB" });
    setIsDialogOpen(false);
    setNewTenantName("");
    setNewTenantRuc("");
    toast.success("Empresa creada exitosamente");
  };

  const handleEditTenant = (tenant: any) => {
    setEditingTenant({
      id: tenant.id,
      name: tenant.name,
      ruc: tenant.ruc,
      primaryColor: tenant.primaryColor || "#2563EB",
    });
    setIsEditDialogOpen(true);
  };

  const handleUpdateTenant = () => {
    if (!editingTenant || !editingTenant.name || !editingTenant.ruc) {
      toast.error("Por favor completa todos los campos");
      return;
    }

    updateTenant(editingTenant.id, {
      name: editingTenant.name,
      ruc: editingTenant.ruc,
      primaryColor: editingTenant.primaryColor,
    });
    setIsEditDialogOpen(false);
    setEditingTenant(null);
    toast.success("Empresa actualizada exitosamente");
  };

  const handleToggleStatus = (tenantId: string) => {
    const t = tenants.find((x) => x.id === tenantId);
    if (!t) return;
    updateTenant(tenantId, { status: t.status === "active" ? "inactive" : "active" });
    toast.success("Estado actualizado");
  };

  const handleDeleteTenant = (tenantId: string) => {
    deleteTenant(tenantId);
    toast.success("Empresa eliminada");
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
            <h1>Gestión de Empresas (Tenants)</h1>
            <p className="text-[#64748B]">
              Administra las empresas registradas en la plataforma
            </p>
          </div>
        </div>

        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button className="gap-2 bg-[#2563EB] hover:bg-[#1E40AF]">
              <Plus className="w-4 h-4" />
              Nueva Empresa
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Crear Nueva Empresa</DialogTitle>
              <DialogDescription>
                Registra una nueva empresa en la plataforma multitenant
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="tenant-name">Nombre de la Empresa</Label>
                <Input
                  id="tenant-name"
                  placeholder="Ej: MiBoleta"
                  value={newTenantName}
                  onChange={(e) => setNewTenantName(e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tenant-ruc">RUC / Tax ID</Label>
                <Input
                  id="tenant-ruc"
                  placeholder="Ej: 20123456789"
                  value={newTenantRuc}
                  onChange={(e) => setNewTenantRuc(e.target.value)}
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                Cancelar
              </Button>
              <Button onClick={handleCreateTenant} className="bg-[#2563EB] hover:bg-[#1E40AF]">
                Crear Empresa
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Edit Tenant Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Editar Empresa</DialogTitle>
            <DialogDescription>
              Modifica los datos de la empresa
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="edit-tenant-name">Nombre de la Empresa</Label>
              <Input
                id="edit-tenant-name"
                placeholder="Ej: MiBoleta"
                value={editingTenant?.name || ""}
                onChange={(e) =>
                  setEditingTenant(
                    editingTenant ? { ...editingTenant, name: e.target.value } : null
                  )
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-tenant-ruc">RUC / Tax ID</Label>
              <Input
                id="edit-tenant-ruc"
                placeholder="Ej: 20123456789"
                value={editingTenant?.ruc || ""}
                onChange={(e) =>
                  setEditingTenant(
                    editingTenant ? { ...editingTenant, ruc: e.target.value } : null
                  )
                }
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-tenant-color">Color Principal</Label>
              <div className="flex gap-2">
                <Input
                  id="edit-tenant-color"
                  type="color"
                  value={editingTenant?.primaryColor || "#2563EB"}
                  onChange={(e) =>
                    setEditingTenant(
                      editingTenant ? { ...editingTenant, primaryColor: e.target.value } : null
                    )
                  }
                  className="w-20 h-10"
                />
                <Input
                  type="text"
                  value={editingTenant?.primaryColor || "#2563EB"}
                  onChange={(e) =>
                    setEditingTenant(
                      editingTenant ? { ...editingTenant, primaryColor: e.target.value } : null
                    )
                  }
                  placeholder="#2563EB"
                  className="flex-1"
                />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setIsEditDialogOpen(false);
                setEditingTenant(null);
              }}
            >
              Cancelar
            </Button>
            <Button onClick={handleUpdateTenant} className="bg-[#2563EB] hover:bg-[#1E40AF]">
              Guardar Cambios
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <StatsCard
          title="Total de Empresas"
          value={tenants.length}
          icon={Building2}
          color="#2563EB"
        />
        <StatsCard
          title="Empresas Activas"
          value={tenants.filter((t) => t.status === "active").length}
          icon={CheckCircle2}
          color="#10B981"
        />
        <StatsCard
          title="Usuarios Totales"
          value={users.length}
          icon={Users}
          color="#8B5CF6"
        />
      </div>

      {/* Tenants Table */}
      <Card>
        <CardHeader>
          <CardTitle>Empresas Registradas</CardTitle>
          <CardDescription>
            Lista completa de empresas en la plataforma
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Empresa</TableHead>
                <TableHead>RUC</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Usuarios</TableHead>
                <TableHead>Documentos</TableHead>
                <TableHead>Creación</TableHead>
                <TableHead>Color</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {tenants.map((tenant) => (
                <TableRow key={tenant.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div
                        className="w-8 h-8 rounded-lg flex items-center justify-center"
                        style={{ backgroundColor: tenant.primaryColor || "#2563EB" }}
                      >
                        <Building2 className="w-4 h-4 text-white" />
                      </div>
                      <div>
                        <p>{tenant.name}</p>
                        <p className="text-[#64748B]">{tenant.id}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>{tenant.ruc}</TableCell>
                  <TableCell>
                    {tenant.status === "active" ? (
                      <Badge className="bg-[#10B981] text-white gap-1">
                        <CheckCircle2 className="w-3 h-3" />
                        Activa
                      </Badge>
                    ) : (
                      <Badge variant="outline" className="text-[#64748B] gap-1">
                        <XCircle className="w-3 h-3" />
                        Inactiva
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell>{getUsersCount(tenant.id)}</TableCell>
                  <TableCell>0</TableCell>
                  <TableCell>{tenant.createdAt}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <div
                        className="w-6 h-6 rounded border border-[rgba(0,0,0,0.1)]"
                        style={{ backgroundColor: tenant.primaryColor || "#2563EB" }}
                      />
                      <span className="text-[#64748B]">
                        {tenant.primaryColor || "#2563EB"}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleToggleStatus(tenant.id)}
                      >
                        {tenant.status === "active" ? (
                          <XCircle className="w-4 h-4 text-[#F59E0B]" />
                        ) : (
                          <CheckCircle2 className="w-4 h-4 text-[#10B981]" />
                        )}
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => handleEditTenant(tenant)}>
                        <Edit className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleDeleteTenant(tenant.id)}
                      >
                        <Trash2 className="w-4 h-4 text-[#EF4444]" />
                      </Button>
                    </div>
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
