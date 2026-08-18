import { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { useCan } from '@/presentation/hooks/useCan';
import { useUrlFilters, useDocumentTitle } from '@/presentation/hooks';
import { ConfirmDialog } from '@/presentation/components/shared/ConfirmDialog';
import { Tenant } from '@/core/domain/entities/Tenant';
import { Button } from '@/presentation/components/ui/button';
import { CheckCircle2, SquareX, Download } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/presentation/components/ui/table';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/presentation/components/ui/card';
import { Badge } from '@/presentation/components/ui/badge';
import { Input } from '@/presentation/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/presentation/components/ui/select';
import { Building2, Search, Pencil, Trash2, Loader2, Eye } from 'lucide-react';
import { PaginationControls } from '@/presentation/components/shared/PaginationControls';
import { StatsCard } from '@/presentation/components/common';
import { reportsRepository } from '@/infrastructure/persistence/repositories';
import { toast } from 'sonner';
import { showApiError } from '@/presentation/utils/showApiError';

export function TenantsListPage() {
    useDocumentTitle('Empresas');
    const navigate = useNavigate();
    const {
        tenants,
        isLoading,
        pagination,
        fetchTenants,
        deleteTenant,
        goToPage,
        changePerPage,
        setSearch: setSearchInStore,
        setStatusFilter,
    } = useTenantsStore();

    // URL-synced filters
    const { filters, setFilters } = useUrlFilters({
        defaultValues: {
            search: '',
            status: 'all',
            page: 1,
        }
    });

    // Local state for search input (for debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [tenantToDelete, setTenantToDelete] = useState<{ id: string; name: string } | null>(null);
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

    // Semilla inicial del input de búsqueda desde la URL. Solo al montar
    // A PROPÓSITO: con `filters.search` en las dependencias, el valor que el
    // debounce escribe en la URL 500 ms después volvería a entrar aquí y
    // pisaría lo que el usuario haya seguido tecleando entre medias.
    useEffect(() => {
        setSearchInput(filters.search);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Fetch tenants when URL filters change
    useEffect(() => {
        setSearchInStore(filters.search);
        setStatusFilter(filters.status === 'all' ? '' : filters.status);
        fetchTenants();
    }, [filters.search, filters.status, filters.page, fetchTenants, setSearchInStore, setStatusFilter]);

    const handleDelete = (id: string, tenantName: string) => {
        setTenantToDelete({ id, name: tenantName });
        setIsConfirmOpen(true);
    };

    const confirmDelete = async () => {
        if (!tenantToDelete) return;
        const success = await deleteTenant(tenantToDelete.id);
        if (success) {
            await fetchTenants();
        }
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            // suspended: 'bg-red-100 text-red-800',
        };

        const labels = {
            active: 'Activo',
            inactive: 'Inactivo',
            // suspended: 'Suspendido',
        };

        return (
            <Badge className={variants[status as keyof typeof variants] || variants.inactive}>
                {labels[status as keyof typeof labels] || status}
            </Badge>
        );
    };

    // Matriz de Accesos: crear / editar / desactivar organizaciones es
    // 'tenants.manage' (solo root), y el backend autoriza con el mismo mapa.
    // Antes se comparaba contra user.role, el respaldo GLOBAL.
    //
    // Editar ya no depende del tenant: bastaba con pertenecer a la empresa
    // (sin mirar el rol), así que a un client se le pintaba el lápiz y la API
    // respondía 403 — el bug de "botón visible -> 403" que motivó la matriz.
    const canManageTenants = useCan('tenants.manage');

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportTenants({
                search: filters.search || undefined,
                status: filters.status !== 'all' ? filters.status : undefined,
            });
            const filename = `organizaciones_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            showApiError(error, 'Error al exportar organizaciones');
        } finally {
            setIsExporting(false);
        }
    };

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
        goToPage(page);
    };

    const handlePerPageChange = (perPage: number) => {
        setFilters({ page: 1 });
        changePerPage(perPage);
    };

    return (
        <div className="container mx-auto py-6 space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold">Organizaciones</h1>
                    <p className="text-gray-500 mt-1 text-sm sm:text-base">
                        Gestiona las organizaciones del sistema
                    </p>
                </div>
                <div className="flex gap-2 flex-wrap">
                    {/* Obs-3: el backend responde 403 a export para no-root (misma
                        ability 'tenants.manage' que crear/editar/eliminar), así que
                        el botón se gatea igual para no mostrar una acción que el
                        backend va a rechazar. */}
                    {canManageTenants && (
                        <Button
                            variant="outline"
                            className="h-9 sm:h-10 px-3 sm:px-4"
                            onClick={handleExport}
                            disabled={isExporting}
                        >
                            {isExporting ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-2 h-4 w-4" />
                            )}
                            Exportar
                        </Button>
                    )}
                    {canManageTenants && (
                        <Button
                            className="h-9 sm:h-10 px-3 sm:px-4"
                            onClick={() => navigate('/tenants/new')}
                        >
                            <Building2 className="mr-2 h-4 w-4" />
                            <span className="hidden xs:inline">Crear </span>Organización
                        </Button>
                    )}
                </div>
            </div>

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
                    title="Empresas Inactivas"
                    value={tenants.filter((t) => t.status === "inactive").length}
                    icon={SquareX}
                    color="red"
                />
                {/* <StatsCard
                title="Usuarios Totales"
                value={users.length}
                icon={Users}
                color="#8B5CF6"
                /> */}
            </div>

            {/* Filters */}
            <Card>
                <CardHeader>
                    <CardTitle>Filtros</CardTitle>
                    <CardDescription>Busca y filtra organizaciones</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Search */}
                        <div className="md:col-span-2">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Buscar por nombre, RUC o razón social..."
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>

                        {/* Status Filter */}
                        <Select value={filters.status} onValueChange={handleStatusChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Filtrar por estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los estados</SelectItem>
                                <SelectItem value="active">Activo</SelectItem>
                                <SelectItem value="inactive">Inactivo</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardContent className="p-0">
                    {/* Sin wrapper propio de overflow-x-auto: el componente Table
                        (ui/table.tsx) ya envuelve el <table> en uno. Tener los dos
                        anidados era redundante y no causaba el corte (eso venía de
                        `main` sin min-w-0, ver RootLayout.tsx), pero duplicaba la
                        barra de scroll sin necesidad. */}
                    <Table>
                        <TableHeader>
                            {/* Encabezado de 2 filas: agrupa las 3 columnas de empleados
                                bajo "Empleados" (rowSpan/colSpan) para poder acortar sus
                                subtítulos a "Inicial/Agregados/Total" sin perder claridad
                                — el pedido del cliente de ver las 3 cifras se mantiene,
                                solo cambia cómo se rotula. Esto es lo que redujo el ancho
                                real de esas 3 columnas (antes ~300px, ahora ~230px). */}
                            <TableRow>
                                <TableHead rowSpan={2} className="w-[50px] min-w-[50px] align-bottom"></TableHead>
                                <TableHead rowSpan={2} className="min-w-[120px] align-bottom">Nombre</TableHead>
                                <TableHead rowSpan={2} className="min-w-[100px] align-bottom">RUC</TableHead>
                                {/* Razón Social y Teléfono son secundarios frente al pedido
                                    explícito del cliente (las 3 cifras de empleados), así
                                    que comparten el breakpoint más alto (xl) entre las dos:
                                    en lg (1024-1279px) ceden el espacio a Inicial/Agregados/
                                    Total en vez de aparecer antes que ellas. */}
                                <TableHead rowSpan={2} className="hidden xl:table-cell min-w-[150px] align-bottom">Razón Social</TableHead>
                                <TableHead rowSpan={2} className="hidden xl:table-cell min-w-[100px] align-bottom">Teléfono</TableHead>
                                <TableHead colSpan={3} className="hidden lg:table-cell text-center">Empleados</TableHead>
                                {/* Solo root: los admin_tenant de cada empresa no son empleados
                                    (regla "admin_tenant domina"), van en su propia columna. */}
                                {canManageTenants && (
                                    <TableHead rowSpan={2} className="hidden lg:table-cell min-w-[90px] text-center align-bottom">Cuentas de Aplicación</TableHead>
                                )}
                                <TableHead rowSpan={2} className="min-w-[80px] align-bottom">Estado</TableHead>
                                <TableHead rowSpan={2} className="text-center min-w-[100px] align-bottom sticky right-0 z-10 bg-background border-l">Acciones</TableHead>
                            </TableRow>
                            <TableRow>
                                <TableHead className="hidden lg:table-cell min-w-[70px] text-center">Inicial</TableHead>
                                <TableHead className="hidden lg:table-cell min-w-[90px] text-center">Agregados</TableHead>
                                <TableHead className="hidden lg:table-cell min-w-[70px] text-center">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={canManageTenants ? 11 : 10} className="text-center py-8">
                                        <Loader2 className="h-6 w-6 animate-spin mx-auto text-gray-400" />
                                        <p className="text-sm text-gray-500 mt-2">Cargando organizaciones...</p>
                                    </TableCell>
                                </TableRow>
                            ) : tenants.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={canManageTenants ? 11 : 10} className="text-center py-8">
                                        <Building2 className="h-12 w-12 mx-auto text-gray-300 mb-2" />
                                        <p className="text-gray-500">No se encontraron organizaciones</p>
                                        {canManageTenants && (
                                            <Button
                                                variant="link"
                                                onClick={() => navigate('/tenants/new')}
                                                className="mt-2"
                                            >
                                                Crear primera organización
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                tenants.map((tenant: Tenant) => (
                                    // `group`: permite que la celda sticky de Acciones (abajo)
                                    // siga el hover de la fila vía group-hover, ya que al tener
                                    // fondo propio (necesario para taparlo al scrollear) el
                                    // hover:bg-muted/50 de TableRow no la alcanza por sí solo.
                                    <TableRow key={tenant.id} className="group">
                                        <TableCell>
                                            {tenant.logo_url ? (
                                                <img
                                                    src={tenant.logo_url}
                                                    alt={tenant.name}
                                                    className="h-10 w-10 rounded object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded bg-blue-100">
                                                    <Building2 className="h-5 w-5 text-blue-600" />
                                                </div>
                                            )}
                                        </TableCell>
                                        {/* max-w + truncate: "Nombre" y "Razón Social" son texto libre
                                            sin tope superior (el min-w solo pone un piso). Una razón
                                            social larga (frecuente en Perú, p.ej. "... Sociedad Anónima
                                            Cerrada") podía ensanchar la tabla lo suficiente para que la
                                            columna sticky de Acciones, al fijarse a la derecha, tapara
                                            visualmente "Total"/"Estado" en la posición de scroll inicial
                                            (se veían recién al scrollear). Con el ancho acotado ya no.

                                            El tope es responsivo a propósito: medido con Chrome sobre
                                            el CSS real, con sidebar expandido y una razón social de 68
                                            caracteres, en xl (1280px) los topes holgados dejaban la
                                            tabla 70px por encima del ancho disponible; con 150/160 el
                                            excedente baja a ~10px (imperceptible). A partir de 2xl sí
                                            sobra espacio, así que ahí se ensanchan y se ve más texto.
                                            El title= mantiene accesible el valor completo siempre. */}
                                        <TableCell className="font-medium max-w-[150px] 2xl:max-w-[180px] truncate" title={tenant.name}>{tenant.name}</TableCell>
                                        <TableCell>{tenant.ruc}</TableCell>
                                        <TableCell className="hidden xl:table-cell max-w-[160px] 2xl:max-w-[220px] truncate" title={tenant.business_name || undefined}>
                                            {tenant.business_name || '-'}
                                        </TableCell>
                                        <TableCell className="hidden xl:table-cell">
                                            {tenant.phone || '-'}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell text-center">
                                            {tenant.initial_employee_count ?? 0}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell text-center">
                                            <Badge className="bg-green-100 text-green-800 font-medium">
                                                +{tenant.subsequent_employee_count ?? 0}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell text-center">
                                            <Badge variant="outline" className="font-medium">
                                                {tenant.current_employee_count ?? 0}
                                            </Badge>
                                        </TableCell>
                                        {canManageTenants && (
                                            <TableCell className="hidden lg:table-cell text-center">
                                                <Badge className="bg-blue-100 text-blue-800 font-medium">
                                                    {tenant.app_accounts_count ?? 0}
                                                </Badge>
                                            </TableCell>
                                        )}
                                        <TableCell>{getStatusBadge(tenant.status)}</TableCell>
                                        <TableCell className="text-center sticky right-0 z-10 bg-background group-hover:bg-muted/50 border-l">
                                            <div className="flex justify-center gap-2">
                                                {/* Obs-3: visible para todos los que ven la lista (incluye
                                                    admin_tenant, de solo lectura). TenantFormPage decide
                                                    internamente si el detalle se abre en modo lectura o
                                                    edición según 'tenants.manage'. */}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => navigate(`/tenants/${tenant.id}`)}
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                                {canManageTenants && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => navigate(`/tenants/${tenant.id}`)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {canManageTenants && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(tenant.id, tenant.name)}
                                                        className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    {pagination && pagination.total > 0 && (
                        <PaginationControls
                            currentPage={pagination.current_page}
                            totalPages={pagination.last_page}
                            total={pagination.total}
                            perPage={pagination.per_page}
                            onPageChange={goToPage}
                            onPerPageChange={changePerPage}
                            disabled={isLoading}
                            perPageOptions={[10, 25, 50, 100]}
                            className="px-6 py-4 border-t"
                        />
                    )}
                </CardContent>
            </Card>

            {/* Confirm Delete Dialog */}
            <ConfirmDialog
                open={isConfirmOpen}
                onOpenChange={setIsConfirmOpen}
                title="Eliminar Tenant"
                description={tenantToDelete ? `¿Estás seguro de eliminar el tenant "${tenantToDelete.name}"?` : ''}
                onConfirm={confirmDelete}
                confirmText="Eliminar"
                variant="destructive"
            />
        </div>
    );
}
export default TenantsListPage;