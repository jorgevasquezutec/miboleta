import { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { useAuthStore } from '@/presentation/stores/authStore';
import { useUrlFilters } from '@/presentation/hooks';
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
import { Building2, Search, Pencil, Trash2, Loader2 } from 'lucide-react';
import { PaginationControls } from '@/presentation/components/shared/PaginationControls';
import { StatsCard } from '@/presentation/components/common';
import { reportsRepository } from '@/infrastructure/persistence/repositories';
import { toast } from 'sonner';

export function TenantsListPage() {
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
    const { user: currentUser } = useAuthStore();

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

    // Sync search input with URL on mount
    useEffect(() => {
        setSearchInput(filters.search);
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

    const canCreateTenant = currentUser?.role === 'root';
    const canEditTenant = (tenantId: string) => {
        if (currentUser?.role === 'root') return true;
        // Admin solo puede editar sus tenants
        return currentUser?.tenants?.some(t => t.id === tenantId) || false;
    };
    const canDeleteTenant = currentUser?.role === 'root';

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
            toast.error('Error al exportar organizaciones');
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
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold">Organizaciones</h1>
                    <p className="text-gray-500 mt-1">
                        Gestiona las organizaciones del sistema
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
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
                    {canCreateTenant && (
                        <Button onClick={() => navigate('/tenants/new')}>
                            <Building2 className="mr-2 h-4 w-4" />
                            Crear Organización
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
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[50px]"></TableHead>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>RUC</TableHead>
                                    <TableHead className="hidden md:table-cell">Razón Social</TableHead>
                                    <TableHead className="hidden lg:table-cell">Teléfono</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead className="text-center">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {isLoading ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center py-8">
                                            <Loader2 className="h-6 w-6 animate-spin mx-auto text-gray-400" />
                                            <p className="text-sm text-gray-500 mt-2">Cargando organizaciones...</p>
                                        </TableCell>
                                    </TableRow>
                                ) : tenants.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center py-8">
                                            <Building2 className="h-12 w-12 mx-auto text-gray-300 mb-2" />
                                            <p className="text-gray-500">No se encontraron organizaciones</p>
                                            {canCreateTenant && (
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
                                        <TableRow key={tenant.id}>
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
                                            <TableCell className="font-medium">{tenant.name}</TableCell>
                                            <TableCell>{tenant.ruc}</TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {tenant.business_name || '-'}
                                            </TableCell>
                                            <TableCell className="hidden lg:table-cell">
                                                {tenant.phone || '-'}
                                            </TableCell>
                                            <TableCell>{getStatusBadge(tenant.status)}</TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex justify-center gap-2">
                                                    {/* <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => navigate(`/tenants/${tenant.id}`)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button> */}
                                                    {canEditTenant(tenant.id) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => navigate(`/tenants/${tenant.id}`)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDeleteTenant && (
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
                    </div>

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