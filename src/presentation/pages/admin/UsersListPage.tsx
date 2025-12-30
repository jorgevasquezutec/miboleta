import { useEffect, useState, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useUsersStore } from "@/presentation/stores/usersStore";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useUrlFilters, useDocumentTitle } from "@/presentation/hooks";
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
import { UserPlus, Search, Eye, Pencil, Trash2, Loader2, Download, Upload } from "lucide-react";
import { toast } from "sonner";
import { Tenant } from "@/core/domain/entities/Tenant";
import { reportsRepository } from "@/infrastructure/persistence/repositories";

export function UsersListPage() {
  useDocumentTitle('Usuarios');
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

  // URL-synced filters
  const { filters, setFilter, setFilters, resetFilters } = useUrlFilters({
    defaultValues: {
      search: '',
      status: 'all',
      tenant_id: '',
      page: 1,
    }
  });

  // Local state for search input (for debounce)
  const [searchInput, setSearchInput] = useState(filters.search);
  const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const [userToDelete, setUserToDelete] = useState<{ id: string; name: string } | null>(null);
  const [isExporting, setIsExporting] = useState(false);
  const debounceTimer = useRef<NodeJS.Timeout | null>(null);

  // Debounce search input -> update URL
  useEffect(() => {
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
    }
    debounceTimer.current = setTimeout(() => {
      if (searchInput !== filters.search) {
        setFilters({ search: searchInput, page: 1 });
      }
    }, 500);
    return () => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }
    };
  }, [searchInput, filters.search, setFilters]);

  // Sync search input with URL on mount
  useEffect(() => {
    setSearchInput(filters.search);
  }, []);

  // Fetch users when URL filters change
  useEffect(() => {
    const statusValue = filters.status === 'all' ? '' : filters.status;
    fetchUsers({
      search: filters.search,
      status: statusValue,
      tenant_id: filters.tenant_id || undefined,
      page: filters.page,
    });
  }, [filters.search, filters.status, filters.tenant_id, filters.page, fetchUsers]);

  const handleDelete = (id: string, userName: string) => {
    setUserToDelete({ id, name: userName });
    setIsConfirmOpen(true);
  };

  const confirmDelete = async () => {
    if (!userToDelete) return;
    try {
      await deleteUser(userToDelete.id);
      toast.success("Usuario eliminado exitosamente");
      await fetchUsers();
    } catch (error) {
      toast.error("Error al eliminar usuario");
    }
  };

  const handleStatusFilterChange = (value: string) => {
    setFilters({ status: value, page: 1 });
  };

  const handleTenantFilterChange = (id: string | null) => {
    setFilters({ tenant_id: id || '', page: 1 });
    if (id) {
      const foundTenant = users.flatMap(u => u.tenants || []).find(t => t.id === id);
      setSelectedTenant(foundTenant ? { id: foundTenant.id, name: foundTenant.name, ruc: foundTenant.ruc || '' } as Tenant : null);
    } else {
      setSelectedTenant(null);
    }
  };

  const handlePageChange = (page: number) => {
    setFilter('page', page);
    goToPage(page);
  };

  const handlePerPageChange = (perPage: number) => {
    setFilter('page', 1);
    changePerPage(perPage);
  };

  const handleResetFilters = () => {
    setSearchInput('');
    setSelectedTenant(null);
    resetFilters();
  };
  const handleExport = async () => {
    setIsExporting(true);
    try {
      const blob = await reportsRepository.exportUsers({
        search: filters.search || undefined,
        tenant_id: filters.tenant_id ? Number(filters.tenant_id) : undefined,
        status: filters.status !== 'all' ? filters.status : undefined,
      });
      const filename = `usuarios_${new Date().toISOString().split('T')[0]}.xlsx`;
      reportsRepository.downloadBlob(blob, filename);
      toast.success('Exportación completada');
    } catch (error) {
      toast.error('Error al exportar usuarios');
    } finally {
      setIsExporting(false);
    }
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
          <div className="flex flex-col gap-4">
            <div>
              <CardTitle className="text-xl sm:text-2xl">Gestión de Usuarios</CardTitle>
              <CardDescription>
                Administra los usuarios del sistema
              </CardDescription>
            </div>
            <div className="flex gap-2 flex-wrap">
              <Button
                variant="outline"
                className="h-9 sm:h-10 px-3 sm:px-4"
                onClick={handleExport}
                disabled={isExporting}
              >
                {isExporting ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Download className="h-4 w-4" />
                )}
                <span className="ml-2 hidden xs:inline">Exportar</span>
              </Button>
              {currentUser?.role === "root" && (
                <>
                  {/* <Button
                    variant="outline"
                    className="h-9 sm:h-10 px-3 sm:px-4"
                    onClick={() => navigate("/users/batch-upload")}
                  >
                    <Upload className="h-4 w-4" />
                    <span className="ml-2 hidden xs:inline">Carga Masiva</span>
                  </Button> */}
                  <Button
                    className="h-9 sm:h-10 px-3 sm:px-4"
                    onClick={() => navigate("/users/new")}
                  >
                    <UserPlus className="h-4 w-4" />
                    <span className="ml-2 hidden xs:inline">Nuevo Usuario</span>
                  </Button>
                </>
              )}
            </div>
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
                  value={searchInput}
                  onChange={(e) => setSearchInput(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>

            {/* Tenant filter */}
            <div>
              <label className="text-sm font-medium mb-2 block">Organización</label>
              <TenantAutocompleteSelector
                value={filters.tenant_id || null}
                onChange={handleTenantFilterChange}
                selectedTenant={selectedTenant}
                placeholder="Todas"
              />
            </div>

            {/* Status filter */}
            <div>
              <label className="text-sm font-medium mb-2 block">Estado</label>
              <Select value={filters.status} onValueChange={handleStatusFilterChange}>
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
                {filters.search || filters.status !== "all"
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
                            {user.tenants && user.tenants.length > 0 ? (
                              <div className="flex flex-col gap-1">
                                {user.tenants
                                  .filter(t => t.supervisor_id)
                                  .map((tenant) => (
                                    <span key={tenant.id} className="text-sm text-gray-700">
                                      {tenant.supervisor?.full_name || tenant.supervisor?.name || 'Asignado'}
                                    </span>
                                  ))}
                                {user.tenants.filter(t => t.supervisor_id).length === 0 && (
                                  <span className="text-muted-foreground text-sm">
                                    Sin supervisores
                                  </span>
                                )}
                              </div>
                            ) : (
                              <span className="text-muted-foreground text-sm">
                                Sin tenants
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

export default UsersListPage;