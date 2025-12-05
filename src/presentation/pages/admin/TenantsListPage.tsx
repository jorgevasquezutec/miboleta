import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTenantsStore } from '@/presentation/stores/tenantsStore';
import { useAuthStore } from '@/presentation/stores/authStore';
import { Tenant } from '@/core/domain/entities/Tenant';
import { Button } from '@/presentation/components/ui/button';
import { CheckCircle2, SquareX } from 'lucide-react';
// import { Users } from 'lucide-react';
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
import { Building2, Search, Eye, Pencil, Trash2, Loader2, ChevronLeft, ChevronRight } from 'lucide-react';
import { StatsCard } from '@/presentation/components/common';

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

    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilterState] = useState<string>('all');
    const debouncedSearch = useDebounce(searchTerm, 500);

    // Initial load
    useEffect(() => {
        fetchTenants();
    }, []);

    // Handle search changes
    useEffect(() => {
        if (debouncedSearch !== undefined) {
            setSearchInStore(debouncedSearch);
            fetchTenants();
        }
    }, [debouncedSearch]);

    // Handle status filter changes
    useEffect(() => {
        setStatusFilter(statusFilter === 'all' ? '' : statusFilter);
        fetchTenants();
    }, [statusFilter]);

    const handleDelete = async (id: string, tenantName: string) => {
        if (!window.confirm(`¿Estás seguro de eliminar el tenant "${tenantName}"?`)) {
            return;
        }

        const success = await deleteTenant(id);
        if (success) {
            await fetchTenants();
        }
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            suspended: 'bg-red-100 text-red-800',
        };

        const labels = {
            active: 'Activo',
            inactive: 'Inactivo',
            suspended: 'Suspendido',
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
                {canCreateTenant && (
                    <Button onClick={() => navigate('/tenants/new')}>
                        <Building2 className="mr-2 h-4 w-4" />
                        Crear Organización
                    </Button>
                )}
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
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>

                        {/* Status Filter */}
                        <Select value={statusFilter} onValueChange={setStatusFilterState}>
                            <SelectTrigger>
                                <SelectValue placeholder="Filtrar por estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los estados</SelectItem>
                                <SelectItem value="active">Activo</SelectItem>
                                <SelectItem value="inactive">Inactivo</SelectItem>
                                <SelectItem value="suspended">Suspendido</SelectItem>
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
                                                {tenant.logo_path ? (
                                                    <img
                                                        src={tenant.logo_path}
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
                        <div className="flex items-center justify-between px-6 py-4 border-t">
                            <div className="flex items-center gap-4">
                                <p className="text-sm text-gray-500">
                                    Mostrando {pagination.from} a {pagination.to} de {pagination.total} organizaciones
                                </p>
                                <Select
                                    value={pagination.per_page.toString()}
                                    onValueChange={(value: string) => changePerPage(parseInt(value))}
                                >
                                    <SelectTrigger className="w-[100px]">
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

                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => goToPage(pagination.current_page - 1)}
                                    disabled={pagination.current_page === 1 || isLoading}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    Anterior
                                </Button>

                                <div className="flex items-center gap-1">
                                    {Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                                        const page = i + 1;
                                        return (
                                            <Button
                                                key={page}
                                                variant={pagination.current_page === page ? 'default' : 'outline'}
                                                size="sm"
                                                onClick={() => goToPage(page)}
                                                disabled={isLoading}
                                                className="w-10"
                                            >
                                                {page}
                                            </Button>
                                        );
                                    })}
                                    {pagination.last_page > 5 && <span className="px-2">...</span>}
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => goToPage(pagination.current_page + 1)}
                                    disabled={pagination.current_page === pagination.last_page || isLoading}
                                >
                                    Siguiente
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
