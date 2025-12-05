import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useUsersStore } from "@/presentation/stores/usersStore";
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
import { UserPlus, Search, Eye, Pencil, Trash2, Loader2, ChevronLeft, ChevronRight } from "lucide-react";
import { toast } from "sonner";

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
    setSearch: setSearchInStore,
    setStatusFilter,
  } = useUsersStore();
  const { user: currentUser } = useAuthStore();
  
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilterState] = useState<string>("all");
  const debouncedSearch = useDebounce(searchTerm, 500);

  // Initial load
  useEffect(() => {
    fetchUsers();
    
    // Debug: Ver el ancho de la pantalla
    console.log('📏 Ancho de pantalla:', window.innerWidth, 'px');
    console.log('Breakpoints Tailwind:');
    console.log('- sm: 640px');
    console.log('- md: 768px (Documento y Tenants)');
    console.log('- lg: 1024px (Email)');
    console.log('- xl: 1280px (Supervisor)');
  }, []);

  // Handle search changes
  useEffect(() => {
    if (debouncedSearch !== undefined) {
      setSearchInStore(debouncedSearch);
    }
  }, [debouncedSearch]);

  const handleDelete = async (id: string, userName: string) => {
    if (!window.confirm(`¿Estás seguro de eliminar a ${userName}?`)) {
      return;
    }

    try {
      await deleteUser(id);
      toast.success("Usuario eliminado exitosamente");
      // Reload current page
      await fetchUsers();
    } catch (error) {
      toast.error("Error al eliminar usuario");
    }
  };

  const handleStatusFilterChange = (value: string) => {
    setStatusFilterState(value);
    if (value === "all") {
      setStatusFilter("");
    } else {
      setStatusFilter(value);
    }
  };

  const handlePerPageChange = (value: string) => {
    changePerPage(parseInt(value));
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

  // Generate page numbers for pagination
  const getPageNumbers = () => {
    if (!pagination) return [];
    
    const { current_page, last_page } = pagination;
    const pages: (number | string)[] = [];
    
    if (last_page <= 7) {
      // Show all pages if 7 or fewer
      for (let i = 1; i <= last_page; i++) {
        pages.push(i);
      }
    } else {
      // Always show first page
      pages.push(1);
      
      if (current_page > 3) {
        pages.push('...');
      }
      
      // Show pages around current page
      const start = Math.max(2, current_page - 1);
      const end = Math.min(last_page - 1, current_page + 1);
      
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      
      if (current_page < last_page - 2) {
        pages.push('...');
      }
      
      // Always show last page
      pages.push(last_page);
    }
    
    return pages;
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
            {(currentUser?.role === "root" || currentUser?.role === "admin") && (
              <Button onClick={() => navigate("/admin/users/new")}>
                <UserPlus className="mr-2 h-4 w-4" />
                Nuevo Usuario
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Filters */}
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="w-full sm:w-[70%] relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
              <Input
                placeholder="Buscar por nombre, email o documento..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10 h-10"
              />
            </div>
            <Select value={statusFilter} onValueChange={handleStatusFilterChange}>
              <SelectTrigger className="w-full sm:w-[30%] h-10">
                <SelectValue placeholder="Estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los estados</SelectItem>
                <SelectItem value="active">Activo</SelectItem>
                <SelectItem value="inactive">Inactivo</SelectItem>
                <SelectItem value="suspended">Suspendido</SelectItem>
              </SelectContent>
            </Select>
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
                {searchTerm || statusFilter !== "all"
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
                                onClick={() => navigate(`/admin/users/${user.id}`)}
                              >
                                <Eye className="h-4 w-4" />
                              </Button>
                              {(currentUser?.role === "root" ||
                                currentUser?.role === "admin") && (
                                <>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                      navigate(`/admin/users/${user.id}/edit`)
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
                <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t">
                  {/* Results info and per page selector */}
                  <div className="flex flex-col sm:flex-row items-center gap-4">
                    <div className="text-sm text-muted-foreground">
                      Mostrando {pagination.from || 0} - {pagination.to || 0} de {pagination.total} usuarios
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-muted-foreground">
                        Resultados por página:
                      </span>
                      <Select 
                        value={pagination.per_page.toString()} 
                        onValueChange={handlePerPageChange}
                      >
                        <SelectTrigger className="w-[80px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="10">10</SelectItem>
                          <SelectItem value="25">25</SelectItem>
                          <SelectItem value="50">50</SelectItem>
                          <SelectItem value="100">100</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  {/* Page navigation */}
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="icon"
                      onClick={() => goToPage(pagination.current_page - 1)}
                      disabled={pagination.current_page === 1}
                    >
                      <ChevronLeft className="h-4 w-4" />
                    </Button>
                    
                    <div className="flex items-center gap-1">
                      {getPageNumbers().map((page, index) => (
                        typeof page === 'number' ? (
                          <Button
                            key={index}
                            variant={page === pagination.current_page ? "default" : "outline"}
                            size="icon"
                            onClick={() => goToPage(page)}
                            className="w-10"
                          >
                            {page}
                          </Button>
                        ) : (
                          <span key={index} className="px-2 text-muted-foreground">
                            {page}
                          </span>
                        )
                      ))}
                    </div>

                    <Button
                      variant="outline"
                      size="icon"
                      onClick={() => goToPage(pagination.current_page + 1)}
                      disabled={pagination.current_page === pagination.last_page}
                    >
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
