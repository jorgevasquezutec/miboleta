import { useEffect, useState, useRef, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { useUsersStore } from "@/presentation/stores/usersStore";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { useCan, useCanAny } from "@/presentation/hooks/useCan";
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
import { UserPlus, Search, Eye, Pencil, Trash2, Loader2, Download, Upload, RotateCcw, LogIn } from "lucide-react";
import { toast } from "sonner";
import { showApiError } from "@/presentation/utils/showApiError";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { canEditTarget } from "@/presentation/utils/userPermissions";
import { formatVacationDays } from "@/presentation/utils";
import { USER_ROLE_DISPLAY_LABELS } from "@/shared/constants";

export function UsersListPage() {
  useDocumentTitle('Usuarios');
  const navigate = useNavigate();
  const {
    users,
    isLoading,
    pagination,
    fetchUsers,
    deleteUser,
    restoreUser,
    goToPage,
    changePerPage,
  } = useUsersStore();
  const { user: currentUser, currentTenant, currentRole } = useAuthStore();
  // Selector aparte del resto (no en la desestructuración de arriba): es una
  // acción estable de zustand, no hace falta que dispare un re-render de todo
  // lo que ya lee `useAuthStore()` ahí.
  const enterImpersonation = useAuthStore((s) => s.enterImpersonation);
  // El navbar (TenantSwitcher) es el único control de empresa para todos los
  // roles. isRootUser solo se usa para el export: ReportsController resuelve
  // la empresa de root por query param y la de no-root por header.
  const isRootUser = currentUser?.role === "root";

  // Permisos según la Matriz de Accesos (el backend autoriza con el mismo mapa),
  // en vez de comparar contra currentUser.role — que es el respaldo GLOBAL
  // (unión de los roles del usuario en todas sus empresas) y dejaba fuera a
  // admin_tenant, que sí puede crear/editar según la matriz.
  const canCreateUser = useCanAny(["users.create_any_role", "users.create_limited_role"]);
  const canUpdateUser = useCan("users.update");
  const canDeleteUser = useCan("users.delete");
  // Habilitar cuentas eliminadas: solo root. Gatea la opción "Eliminados" del
  // filtro de Estado Y el botón Habilitar — sin ella, la papelera no existe
  // para este usuario.
  const canRestoreUser = useCan("users.restore");
  const canBulkUpload = useCan("users.bulk_upload");
  const canExportUsers = useCan("users.export");
  const canExportAppAccounts = useCan("reports.app_accounts_export");
  // "Iniciar sesión como" (impersonation, ver CONTRATO-IMPERSONATION): solo
  // root, vía la Matriz de Accesos igual que el resto de estos flags.
  const canImpersonate = useCan("users.impersonate");

  // URL-synced filters
  const { filters, setFilter, setFilters } = useUrlFilters({
    defaultValues: {
      search: '',
      status: 'all',
      page: 1,
    }
  });

  // ¿Estamos viendo la papelera? Deriva del mismo filtro de Estado que la UI,
  // para que no haya un segundo estado que pueda desincronizarse con el Select.
  // Se exige canRestoreUser porque el filtro viaja en la URL: sin esto, un
  // no-root que abra un enlace con ?status=deleted (o que herede la URL al
  // cambiar de sesión) dispararía la consulta de papelera y comería un 403.
  const isTrashView = filters.status === 'deleted' && canRestoreUser;

  // Local state for search input (for debounce)
  const [searchInput, setSearchInput] = useState(filters.search);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const [userToDelete, setUserToDelete] = useState<{ id: string; name: string } | null>(null);
  const [isRestoreConfirmOpen, setIsRestoreConfirmOpen] = useState(false);
  const [userToRestore, setUserToRestore] = useState<{ id: string; name: string } | null>(null);
  const [isExporting, setIsExporting] = useState(false);
  const [isExportingAppAccounts, setIsExportingAppAccounts] = useState(false);
  // Id del usuario cuyo botón "Iniciar sesión como" está en vuelo. Solo gatea
  // el spinner de SU fila (no el `isLoading` global de authStore, que
  // enterImpersonation también toca): con `isLoading` global, cualquier otro
  // control de la página que lo mire quedaría deshabilitado de rebote por una
  // acción ajena a él.
  const [impersonatingId, setImpersonatingId] = useState<string | null>(null);
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

  // Semilla inicial del input de búsqueda desde la URL. Solo al montar
  // A PROPÓSITO: con `filters.search` en las dependencias, el valor que el
  // debounce escribe en la URL 500 ms después volvería a entrar aquí y
  // pisaría lo que el usuario haya seguido tecleando entre medias.
  useEffect(() => {
    setSearchInput(filters.search);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Ref a la empresa activa previa. Sirve para detectar el cambio de empresa
  // desde el navbar (currentTenant) dentro del efecto de abajo y resetear la
  // página a 1 sin disparar un doble fetch (uno con la página vieja y otro
  // con page=1): si cambió la empresa y no estamos en la página 1, solo
  // actualizamos la URL (page=1) y salimos; el propio cambio de
  // `filters.page` vuelve a disparar el efecto, ya con el ref actualizado, y
  // ahí sí se hace el fetch real.
  const prevTenantIdRef = useRef<string | undefined>(currentTenant?.id);

  // Un solo efecto para root y no-root: UserController::index ya scopea a la
  // empresa activa del switcher (header X-Tenant-Ids), así que no se manda
  // `tenant_id` y el header hace el trabajo. Antes había dos efectos y un
  // dropdown local de empresa porque el backend ignoraba el header; eso era
  // un workaround del bug, no una feature.
  //
  // useTenantAwareEffect recarga cuando cambia el filtro global, que
  // TenantSwitcher sincroniza junto con currentTenant para ambos roles. Root
  // en modo "Todas las empresas" no manda header y el backend no filtra:
  // mismo resultado que antes.
  // Recarga la lista con los filtros y la página que el usuario tiene puestos.
  // Se usa también tras eliminar o habilitar: llamar a fetchUsers() sin
  // argumentos volvía a
  // la página 1 sin búsqueda ni filtro de estado, y parecía que la lista "no se
  // actualizaba" cuando en realidad cambiaba de sitio.
  //
  // 'deleted' es un valor más del Select de Estado en la UI, pero en el backend
  // son dos listados disjuntos: ?deleted=1 (onlyTrashed) ignora `status`. De ahí
  // las dos reglas de abajo:
  //
  // 1) 'deleted' nunca viaja como status. O es la papelera —y entonces va en el
  //    flag `deleted`—, o el usuario no puede verla y se degrada a "sin filtro"
  //    en vez de pedir un status que no existe en BD.
  // 2) `deleted` se manda SIEMPRE, incluso en false: el store mezcla los params
  //    sobre currentFilters, así que omitirlo dejaría pegado el deleted=true
  //    anterior al volver a "Activo".
  const refreshUsers = useCallback(() => {
    return fetchUsers({
      search: filters.search,
      status:
        filters.status === 'all' || filters.status === 'deleted' ? '' : filters.status,
      deleted: isTrashView,
      page: filters.page,
    });
  }, [fetchUsers, filters.search, filters.status, isTrashView, filters.page]);

  useTenantAwareEffect(() => {
    const tenantChanged = prevTenantIdRef.current !== currentTenant?.id;
    prevTenantIdRef.current = currentTenant?.id;

    if (tenantChanged && filters.page !== 1) {
      setFilter('page', 1);
      return;
    }

    refreshUsers();
  }, [filters.search, filters.status, isTrashView, filters.page, currentTenant?.id, refreshUsers]);

  const handleDelete = (id: string, userName: string) => {
    setUserToDelete({ id, name: userName });
    setIsConfirmOpen(true);
  };

  const confirmDelete = async () => {
    if (!userToDelete) return;
    try {
      await deleteUser(userToDelete.id);
      toast.success("Usuario eliminado exitosamente");
      await refreshUsers();
    } catch (error) {
      showApiError(error, "Error al eliminar usuario");
    }
  };

  const handleRestore = (id: string, userName: string) => {
    setUserToRestore({ id, name: userName });
    setIsRestoreConfirmOpen(true);
  };

  const confirmRestore = async () => {
    if (!userToRestore) return;
    try {
      await restoreUser(userToRestore.id);
      toast.success("Usuario habilitado exitosamente");
      // refreshUsers() y no fetchUsers(): el filtrado optimista del store deja
      // la fila fuera pero no corrige paginación ni totales, y hay que volver
      // conservando el filtro de papelera y la página actual.
      await refreshUsers();
    } catch (error) {
      showApiError(error, "Error al habilitar usuario");
    }
  };

  const handleImpersonate = async (id: string) => {
    setImpersonatingId(id);
    try {
      await enterImpersonation(id);
      // No hay nada más que hacer: enterImpersonation() ya dispara la
      // recarga dura (window.location.href) en cuanto el backend confirma,
      // así que este componente ni siquiera llega a desmontarse limpio.
    } catch (error) {
      setImpersonatingId(null);
      showApiError(error, "No se pudo iniciar sesión como este usuario");
    }
  };

  const handleStatusFilterChange = (value: string) => {
    setFilters({ status: value, page: 1 });
  };

  const handlePageChange = (page: number) => {
    setFilter('page', page);
    goToPage(page);
  };

  const handlePerPageChange = (perPage: number) => {
    setFilter('page', 1);
    changePerPage(perPage);
  };

  const handleExport = async () => {
    setIsExporting(true);
    try {
      // Para no-root no se manda tenant_id: ReportsController::exportUsers
      // resuelve la empresa activa desde el header (ActiveTenantResolver),
      // igual que la lista. Root sí lo manda, porque para root el resolver
      // lee el query param y no el header.
      const tenantIdParam = isRootUser && currentTenant
        ? Number(currentTenant.id)
        : undefined;
      const blob = await reportsRepository.exportUsers({
        search: filters.search || undefined,
        tenant_id: tenantIdParam,
        status: filters.status !== 'all' ? filters.status : undefined,
      });
      const filename = `usuarios_${new Date().toISOString().split('T')[0]}.xlsx`;
      reportsRepository.downloadBlob(blob, filename);
      toast.success('Exportación completada');
    } catch (error) {
      showApiError(error, 'Error al exportar usuarios');
    } finally {
      setIsExporting(false);
    }
  };

  const handleExportAppAccounts = async () => {
    setIsExportingAppAccounts(true);
    try {
      // Sin filtros: ReportsController::exportAppAccounts scopea a la empresa
      // activa en el backend, igual que el resto de exports no-root.
      const blob = await reportsRepository.exportAppAccounts();
      const filename = `cuentas_aplicacion_${new Date().toISOString().split('T')[0]}.xlsx`;
      reportsRepository.downloadBlob(blob, filename);
      toast.success('Exportación completada');
    } catch (error) {
      showApiError(error, 'Error al exportar cuentas de aplicación');
    } finally {
      setIsExportingAppAccounts(false);
    }
  };


  const getRoleBadgeStyle = (role: string) => {
    switch (role) {
      case "root":
        return { backgroundColor: "#a855f7", color: "white", borderColor: "transparent" };
      case "admin":
        return { backgroundColor: "#3b82f6", color: "white", borderColor: "transparent" };
      case "admin_tenant":
        return { backgroundColor: "#8b5cf6", color: "white", borderColor: "transparent" };
      case "client":
        return { backgroundColor: "#22c55e", color: "white", borderColor: "transparent" };
      case "aprobador":
        return { backgroundColor: "#f59e0b", color: "white", borderColor: "transparent" };
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
              {canExportUsers && (
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
              )}
              {canExportAppAccounts && (
                <Button
                  variant="outline"
                  className="h-9 sm:h-10 px-3 sm:px-4"
                  onClick={handleExportAppAccounts}
                  disabled={isExportingAppAccounts}
                >
                  {isExportingAppAccounts ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Download className="h-4 w-4" />
                  )}
                  <span className="ml-2 hidden xs:inline">Cuentas de Aplicación</span>
                </Button>
              )}
              {canBulkUpload && (
                <Button
                  variant="outline"
                  className="h-9 sm:h-10 px-3 sm:px-4"
                  onClick={() => navigate("/users/batch-upload")}
                >
                  <Upload className="h-4 w-4" />
                  <span className="ml-2 hidden xs:inline">Carga Masiva</span>
                </Button>
              )}
              {canCreateUser && (
                <Button
                  className="h-9 sm:h-10 px-3 sm:px-4"
                  onClick={() => navigate("/users/new")}
                >
                  <UserPlus className="h-4 w-4" />
                  <span className="ml-2 hidden xs:inline">Nuevo Usuario</span>
                </Button>
              )}
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Filters */}
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
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

            {/* Sin filtro de Organización: el navbar (TenantSwitcher) es el
                único control de empresa, para root y no-root. El dropdown que
                había aquí existía porque el backend ignoraba X-Tenant-Ids; ya
                no hace falta y su placeholder "Todas" era engañoso (para
                no-root nunca hubo vista multi-empresa). */}

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
                  {/* La papelera vive aquí y no en una ruta aparte porque es
                      el mismo listado con las mismas columnas; y solo la ve
                      root (users.restore). "Eliminados" no es un status: el
                      backend lo traduce a ?deleted=1 (onlyTrashed). */}
                  {canRestoreUser && (
                    <SelectItem value="deleted">Eliminados</SelectItem>
                  )}
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
              {/* Responsive Table. Sin overflow-x-auto propio: el componente
                  Table (ui/table.tsx) ya envuelve el <table> en uno; tener
                  los dos anidados era redundante. */}
              <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="whitespace-nowrap">Nombre</TableHead>
                        <TableHead className="whitespace-nowrap">Documento</TableHead>
                        <TableHead className="whitespace-nowrap">Tenants / Rol</TableHead>
                        {/* Siempre visible (sin breakpoint): esconder estas 4 cifras detrás
                            de un breakpoint que el cliente no ve equivale a no haberlas
                            hecho (ya pasó con otra columna en una fase anterior). La tabla
                            ya scrollea horizontalmente (overflow-x-auto) si no entra. */}
                        <TableHead className="whitespace-nowrap">
                          Vacaciones
                          {/* Modo "Todas las empresas" (root): se muestra el
                              saldo de la empresa PRIMARIA (★) de cada usuario —
                              el saldo es POR EMPRESA (hire_date/régimen propios
                              de cada una), y cada celda etiqueta de qué empresa
                              es su cifra para que las filas no se comparen entre
                              sí por error. */}
                          {!currentTenant && (
                            <span
                              className="block text-[10px] font-normal text-muted-foreground normal-case leading-tight"
                              title="En modo Todas las empresas se muestra el saldo de la empresa principal (★) de cada usuario. Selecciona una empresa en el selector superior para ver los saldos de esa empresa."
                            >
                              saldo de la empresa principal ★
                            </span>
                          )}
                        </TableHead>
                        <TableHead className="whitespace-nowrap hidden lg:table-cell">Supervisor</TableHead>
                        <TableHead className="whitespace-nowrap">Estado</TableHead>
                        {/* sticky right-0: sin esto, con todas las columnas visibles
                            (Nombre+email, Tenants/Rol con badges, Vacaciones 2x2,
                            Supervisor, Estado) la tabla fácilmente supera los ~1100px
                            y "Acciones" quedaba solo alcanzable scrolleando hasta el
                            final — ahora los botones editar/ver/eliminar siempre están
                            a la vista. */}
                        <TableHead className="text-center whitespace-nowrap sticky right-0 z-10 bg-background border-l">Acciones</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {users.map((user) => (
                        // `group`: la celda sticky de Acciones necesita fondo propio
                        // para tapar el contenido al scrollear, así que el
                        // hover:bg-muted/50 de TableRow no la alcanza por sí solo;
                        // group-hover en la celda (más abajo) lo compensa.
                        <TableRow key={user.id} className="group">
                          <TableCell className="font-medium">
                            {/* max-w + truncate: nombre completo + email son texto libre sin
                                tope. Verificando el sticky de "Acciones" en Tenants encontré
                                que una columna sin tope puede ensanchar la tabla lo suficiente
                                para que "Acciones" (sticky) tape visualmente la columna vecina
                                en la posición de scroll inicial. Acotamos aquí por la misma
                                razón — ver TenantsListPage.tsx para el detalle. */}
                            <div className="max-w-[200px]">
                              <div className="whitespace-nowrap truncate" title={user.full_name || `${user.name} ${user.last_name || ""}`}>{user.full_name || `${user.name} ${user.last_name || ""}`}</div>
                              <div className="text-xs text-muted-foreground whitespace-nowrap truncate" title={user.email}>{user.email}</div>
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
                            <div className="flex gap-1 flex-wrap min-w-[220px]">
                              {user.role === "root" ? (
                                <Badge
                                  style={getRoleBadgeStyle("root")}
                                  className="text-xs whitespace-nowrap"
                                >
                                  root · plataforma
                                </Badge>
                              ) : user.tenants && user.tenants.length > 0 ? (
                                user.tenants.map((tenant) => (
                                  <Badge
                                    key={tenant.id}
                                    variant={tenant.is_primary ? "default" : "outline"}
                                    className="text-xs whitespace-nowrap"
                                  >
                                    {tenant.name}
                                    {tenant.is_primary ? " ★" : null}
                                    {tenant.role && (
                                      <span className="opacity-80"> · {USER_ROLE_DISPLAY_LABELS[tenant.role as keyof typeof USER_ROLE_DISPLAY_LABELS] ?? tenant.role}</span>
                                    )}
                                  </Badge>
                                ))
                              ) : (
                                <span className="text-muted-foreground text-sm">
                                  Sin tenants
                                </span>
                              )}
                            </div>
                          </TableCell>
                          <TableCell className="whitespace-nowrap">
                            {/* B3: Pendientes/Gozadas/Truncas/Saldo (vocabulario del
                                cliente, mensaje del 31/07/2026) para la empresa activa
                                del switcher — mismo criterio que ya scopea el resto de
                                este listado. `null` = root en modo "todas las empresas":
                                el saldo depende de la empresa (hire_date propio de cada
                                una), así que no hay una cifra única correcta y no se
                                inventa una suma entre empresas. */}
                            {user.vacation_balance ? (
                              <div>
                                {/* Etiqueta de QUÉ empresa es el saldo: en modo
                                    global cada fila puede traer una empresa
                                    distinta (la primaria de cada usuario), y en
                                    multi-empresa evita leer la cifra como "el
                                    saldo del usuario" a secas. */}
                                {(!currentTenant || (user.tenants?.length ?? 0) > 1) && user.vacation_balance.tenant_name && (
                                  <div
                                    className="text-[10px] text-muted-foreground max-w-[130px] truncate leading-tight"
                                    title={`Saldo en ${user.vacation_balance.tenant_name}${user.vacation_balance.is_primary ? " (empresa principal)" : ""}`}
                                  >
                                    {user.vacation_balance.is_primary ? "★ " : ""}
                                    {user.vacation_balance.tenant_name}
                                  </div>
                                )}
                                <div className="grid grid-cols-2 gap-x-2 gap-y-0.5 text-xs min-w-[130px]">
                                <span title="Vacaciones Pendientes">
                                  <span className="text-muted-foreground">Pend.</span>{" "}
                                  <span className="font-medium">
                                    {formatVacationDays(user.vacation_balance.pending)}
                                  </span>
                                </span>
                                <span title="Vacaciones Gozadas">
                                  <span className="text-muted-foreground">Goz.</span>{" "}
                                  <span className="font-medium">
                                    {formatVacationDays(user.vacation_balance.taken)}
                                  </span>
                                </span>
                                <span title="Vacaciones Truncas">
                                  <span className="text-muted-foreground">Trunc.</span>{" "}
                                  <span className="font-medium">
                                    {formatVacationDays(user.vacation_balance.truncated)}
                                  </span>
                                </span>
                                <span title="Saldo Vacaciones">
                                  <span className="text-muted-foreground">Saldo</span>{" "}
                                  <span className="font-semibold">
                                    {formatVacationDays(user.vacation_balance.balance)}
                                  </span>
                                </span>
                                </div>
                              </div>
                            ) : (
                              <span
                                className="text-muted-foreground text-sm"
                                title="Este usuario no pertenece a ninguna empresa visible, no tiene saldo de vacaciones"
                              >
                                —
                              </span>
                            )}
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
                          <TableCell className="text-center sticky right-0 z-10 bg-background group-hover:bg-muted/50 border-l">
                            <div className="flex justify-center gap-2 whitespace-nowrap">
                              <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => navigate(`/users/${user.id}`)}
                              >
                                <Eye className="h-4 w-4" />
                              </Button>
                              {/* Condición propia, no reusada de Editar/Eliminar (mismo
                                  criterio de C2, justo abajo): 'users.impersonate' es una
                                  ability distinta y exclusiva de root, y colgar este botón
                                  de otra condición se la colaría a quien no debe tenerla.
                                  Se oculta además en la papelera, sobre uno mismo y sobre
                                  otro root: el backend devuelve 403 en los tres casos (ver
                                  CONTRATO-IMPERSONATION), así que no se ofrece un botón
                                  condenado a fallar. */}
                              {!isTrashView && canImpersonate && user.role !== "root" && user.id !== currentUser?.id && (
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  title="Iniciar sesión como este usuario"
                                  disabled={impersonatingId === user.id}
                                  onClick={() => handleImpersonate(user.id)}
                                >
                                  {impersonatingId === user.id ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                  ) : (
                                    <LogIn className="h-4 w-4" />
                                  )}
                                </Button>
                              )}
                              {/* C2: Editar y Eliminar son botones independientes, no un
                                  bloque OR. Antes compartían una sola condición
                                  (canUpdateUser || canDeleteUser), así que a un
                                  admin_tenant (que tiene users.update pero NO
                                  users.delete) se le colaba el basurero. */}
                              {/* En la papelera no se edita ni se vuelve a
                                  eliminar: la única acción sobre una cuenta
                                  eliminada es habilitarla. Editar sobre una
                                  fila con deleted_at daría 404 (el scope
                                  global la esconde de findOrFail). */}
                              {!isTrashView && canUpdateUser && canEditTarget(user, currentUser?.id, currentRole, currentTenant?.id) && (
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  onClick={() =>
                                    navigate(`/users/${user.id}/edit`)
                                  }
                                >
                                  <Pencil className="h-4 w-4" />
                                </Button>
                              )}
                              {!isTrashView && canDeleteUser && (
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  onClick={() =>
                                    handleDelete(user.id, user.full_name || user.name)
                                  }
                                >
                                  <Trash2 className="h-4 w-4 text-destructive" />
                                </Button>
                              )}
                              {/* Condición propia y no reusada de la de
                                  Eliminar, por la misma razón que C2 (ver
                                  arriba): son abilities distintas y colgar
                                  este botón de canDeleteUser lo colaría a
                                  quien no puede habilitar. `deleted_at` como
                                  guarda extra al flag de vista, para no pintar
                                  Habilitar sobre una fila activa si alguna vez
                                  los listados se mezclan. */}
                              {isTrashView && canRestoreUser && user.deleted_at && (
                                <Button
                                  variant="ghost"
                                  size="icon"
                                  title="Habilitar usuario"
                                  onClick={() =>
                                    handleRestore(user.id, user.full_name || user.name)
                                  }
                                >
                                  <RotateCcw className="h-4 w-4 text-primary" />
                                </Button>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
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

      {/* Confirm Restore Dialog */}
      <ConfirmDialog
        open={isRestoreConfirmOpen}
        onOpenChange={setIsRestoreConfirmOpen}
        title="Habilitar Usuario"
        description={userToRestore ? `¿Habilitar la cuenta de ${userToRestore.name}? Recuperará su acceso, sus empresas y sus documentos.` : ''}
        onConfirm={confirmRestore}
        confirmText="Habilitar"
      />
    </div>
  );
}

export default UsersListPage;