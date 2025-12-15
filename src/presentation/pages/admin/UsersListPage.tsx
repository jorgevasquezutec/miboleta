import { useEffect, useState, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useUsersStore } from "@/presentation/stores/usersStore";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
import { useAuthStore } from "@/presentation/stores/authStore";
import { Button } from "@/presentation/components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/presentation/components/ui/table";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/presentation/components/ui/card";
import { Badge } from "@/presentation/components/ui/badge";
import { Input } from "@/presentation/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/presentation/components/ui/select";
import { UserPlus, Search, Eye, Pencil, Trash2, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Tenant } from "@/core/domain/entities/Tenant";

// Debounce helper
function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}

export function UsersListPage() {
  const navigate = useNavigate();
  const {
    users,
    isLoading,
    pagination,
    fetchUsers,
    deleteUser,
    goToPage,
    changePerPage,
  } = useUsersStore();
  const { user: currentUser } = useAuthStore();


  const [searchTerm, setSearchTerm] = useState("");
  const [localStatusFilter, setLocalStatusFilter] = useState<"all" | "active" | "inactive">("all");
  const [tenantFilter, setTenantFilter] = useState<string | null>(null);
  const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const [userToDelete, setUserToDelete] = useState<{ id: string; name: string } | null>(null);
  const debouncedSearch = useDebounce(searchTerm, 500);
  const isFirstRender = useRef(true);

  // Single useEffect that handles initial load and filter changes
  useEffect(() => {
    // On first render, just load data
    if (isFirstRender.current) {
      isFirstRender.current = false;
      fetchUsers();
      return;
    }

    // On subsequent renders (when filters change), call fetchUsers with both params
    const statusValue = localStatusFilter === "all" ? "" : localStatusFilter;
    fetchUsers({
      search: debouncedSearch,
      status: statusValue,
      tenant_id: tenantFilter || undefined,
      page: 1 // Reset to page 1 when filters change
    });
  }, [debouncedSearch, localStatusFilter, tenantFilter]);


  const handleDelete = (id: string, userName: string) => {
    setUserToDelete({ id, name: userName });
    setIsConfirmOpen(true);
  };

  const confirmDelete = async () => {
    if (!userToDelete) return;
    try {
      await deleteUser(userToDelete.id);
      toast.success("Usuario eliminado exitosamente");
      // Reload current page
      await fetchUsers();
    } catch (error) {
      toast.error("Error al eliminar usuario");
    }
  };

  const handleStatusFilterChange = (value: string) => {
    // Just update local state, the useEffect will handle the fetch
    setLocalStatusFilter(value as "all" | "active" | "inactive");
  };

  const handlePageChange = (page: number) => {
    goToPage(page);
  };

  const handlePerPageChange = (perPage: number) => {
    changePerPage(perPage);
  };


  const getRoleBadgeStyle = (role: string) => {
    switch (role) {
      case "root":
        return { backgroundColor: "#a855f7", color: "white", borderColor: "transparent" };
      case "admin":
        return { backgroundColor: "#3b82f6", color: "white", borderColor: "transparent" };
      case "client":
        return { backgroundColor: "#22c55e", color: "white", borderColor: "transparent" };
      default:
        return { backgroundColor: "#6b7280", color: "white", borderColor: "transparent" };
    }
  };

  const getStatusBadgeStyle = (status: string) => {
    switch (status) {
      case "active":
        return { backgroundColor: "#22c55e", color: "white", borderColor: "transparent" };
      case "inactive":
        return { backgroundColor: "#6b7280", color: "white", borderColor: "transparent" };
      case "suspended":
        return { backgroundColor: "#ef4444", color: "white", borderColor: "transparent" };
      default:
        return { backgroundColor: "#6b7280", color: "white", borderColor: "transparent" };
    }
  };

  return (
    <div className="container mx-auto py-6 space-y-6">
      <Card>
        <CardHeader>
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
              <CardTitle>Gestión de Usuarios</CardTitle>
              <CardDescription>
                Administra los usuarios del sistema
              </CardDescription>
            </div>
            {currentUser?.role === "root" && (
              <Button onClick={() => navigate("/users/new")}>
                <UserPlus className="mr-2 h-4 w-4" />
                Nuevo Usuario
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Filters */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            {/* Search - takes 2 columns */}
            <div className="md:col-span-2">
              <label className="text-sm font-medium mb-2 block">Buscar</label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input
                  placeholder="Nombre, email o documento..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>

            {/* Tenant filter */}
            <div>
              <label className="text-sm font-medium mb-2 block">Organización</label>
              <TenantAutocompleteSelector
                value={tenantFilter}
                onChange={(id) => {
                  setTenantFilter(id);
                  if (id) {
                    const foundTenant = users.flatMap(u => u.tenants || []).find(t => t.id === id);
                    setSelectedTenant(foundTenant ? { id: foundTenant.id, name: foundTenant.name, ruc: foundTenant.ruc || '' } as Tenant : null);
                  } else {
                    setSelectedTenant(null);
                  }
                }}
                selectedTenant={selectedTenant}
                placeholder="Todas"
              />
            </div>

            {/* Status filter */}
            <div>
              <label className="text-sm font-medium mb-2 block">Estado</label>
              <Select value={localStatusFilter} onValueChange={handleStatusFilterChange}>
                <SelectTrigger>
                  <SelectValue placeholder="Todos" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos</SelectItem>
                  <SelectItem value="active">Activo</SelectItem>
                  <SelectItem value="inactive">Inactivo</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {/* Loading State */}
          {isLoading ? (
            <div className="text-center py-8">
              <Loader2 className="h-8 w-8 animate-spin mx-auto mb-2 text-muted-foreground" />
              <p className="text-muted-foreground">Cargando usuarios...</p>
            </div>
          ) : users.length === 0 ? (
            <div className="text-center py-8">
              <p className="text-muted-foreground">
                {searchTerm || localStatusFilter !== "all"
                  ? "No se encontraron usuarios"
                  : "No hay usuarios registrados"}
              </p>
            </div>
          ) : (
            <>
              {/* Responsive Table */}
              <div className="w-full overflow-x-auto">
                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="whitespace-nowrap">Nombre</TableHead>
                        <TableHead className="whitespace-nowrap">Documento</TableHead>
                        <TableHead className="whitespace-nowrap">Rol</TableHead>
                        <TableHead className="whitespace-nowrap">Tenants</TableHead>
                        <TableHead className="whitespace-nowrap hidden lg:table-cell">Supervisor</TableHead>
                        <TableHead className="whitespace-nowrap">Estado</TableHead>
                        <TableHead className="text-center whitespace-nowrap">Acciones</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {users.map((user) => (
                        <TableRow key={user.id}>
                          <TableCell className="font-medium">
                            <div>
                              <div className="whitespace-nowrap">{user.full_name || `${user.name} ${user.last_name || ""}`}</div>
                              <div className="text-xs text-muted-foreground whitespace-nowrap">{user.email}</div>
                            </div>
                          </TableCell>
                          <TableCell className="whitespace-nowrap">
                            {user.document_text ? (
                              <span className="text-sm">
                                {user.document_type}: {user.document_text}
                              </span>
                            ) : (
                              <span className="text-muted-foreground text-sm">
                                Sin documento
                              </span>
                            )}
                          </TableCell>
                          <TableCell>
                            <Badge
                              style={getRoleBadgeStyle(user.role || "")}
                            >
                              {user.role}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <div className="flex gap-1 flex-wrap min-w-[200px]">
                              {user.tenants && user.tenants.length > 0 ? (
                                user.tenants.map((tenant) => (
                                  <Badge
                                    key={tenant.id}
                                    variant={tenant.is_primary ? "default" : "outline"}
                                    className="text-xs whitespace-nowrap"
                                  >
                                    {tenant.name}
                                    {tenant.is_primary && " ★"}
                                  </Badge>
                                ))
                              ) : (
                                <span className="text-muted-foreground text-sm">
                                  Sin tenants
                                </span>
                              )}
                            </div>
                          </TableCell>
                          <TableCell className="whitespace-nowrap hidden lg:table-cell">
                            {user.immediate_supervisor ? (
                              <span className="text-sm">
                                {user.immediate_supervisor.full_name || user.immediate_supervisor.name}
                              </span>
                            ) : (
                              <span className="text-muted-foreground text-sm">
                                Sin supervisor
                              </span>
                            )}
                          </TableCell>
                          <TableCell>
                            <Badge
                              style={getStatusBadgeStyle(user.status || "active")}
                            >
                              {user.status || "active"}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-center">
                            <div className="flex justify-center gap-2 whitespace-nowrap">
                              <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => navigate(`/users/${user.id}`)}
                              >
                                <Eye className="h-4 w-4" />
                              </Button>
                              {currentUser?.role === "root" && (
                                <>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                      navigate(`/users/${user.id}/edit`)
                                    }
                                  >
                                    <Pencil className="h-4 w-4" />
                                  </Button>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                      handleDelete(user.id, user.full_name || user.name)
                                    }
                                  >
                                    <Trash2 className="h-4 w-4 text-destructive" />
                                  </Button>
                                </>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </div>

              {/* Pagination Controls */}
              {pagination && (
                <PaginationControls
                  currentPage={pagination.current_page}
                  totalPages={pagination.last_page}
                  total={pagination.total}
                  perPage={pagination.per_page}
                  onPageChange={handlePageChange}
                  onPerPageChange={handlePerPageChange}
                  disabled={isLoading}
                  perPageOptions={[10, 25, 50, 100]}
                  className="pt-4 border-t"
                />
              )}
            </>
          )}
        </CardContent>
      </Card>

      {/* Confirm Delete Dialog */}
      <ConfirmDialog
        open={isConfirmOpen}
        onOpenChange={setIsConfirmOpen}
        title="Eliminar Usuario"
        description={userToDelete ? `¿Estás seguro de eliminar a ${userToDelete.name}?` : ''}
        onConfirm={confirmDelete}
        confirmText="Eliminar"
        variant="destructive"
      />
    </div>
  );
}
