import { useEffect, useState, useCallback, useRef } from "react";
import { format, parse } from "date-fns";
import {
    FileText,
    RefreshCw,
    Download,
    Search,
    Filter,
    LogIn,
    LogOut,
    FileSignature,
    Eye,
    Trash2,
    UserPlus,
    Building2,
    Calendar,
    Upload,
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/presentation/components/ui/table";
import { Badge } from "@/presentation/components/ui/badge";
import { Input } from "@/presentation/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import { Skeleton } from "@/presentation/components/ui/skeleton";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { useAuthStore } from "@/presentation/stores/authStore";
import { useUrlFilters } from "@/presentation/hooks";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { AuditLog, ReportFilters } from "@/core/domain/entities";
import { Tenant } from "@/core/domain/entities/Tenant";

// Map action to icon
const getActionIcon = (action: string) => {
    if (action.startsWith('user.login') && !action.includes('failed')) return <LogIn className="w-4 h-4 text-green-500" />;
    if (action.includes('login_failed')) return <LogIn className="w-4 h-4 text-red-500" />;
    if (action.startsWith('user.logout')) return <LogOut className="w-4 h-4 text-gray-500" />;
    if (action.startsWith('user.created') || action.startsWith('user.updated')) return <UserPlus className="w-4 h-4 text-blue-500" />;
    if (action.startsWith('user.deleted')) return <Trash2 className="w-4 h-4 text-red-500" />;
    if (action.startsWith('document.signed')) return <FileSignature className="w-4 h-4 text-green-500" />;
    if (action.startsWith('document.viewed')) return <Eye className="w-4 h-4 text-blue-500" />;
    if (action.startsWith('document.uploaded') || action.startsWith('batch.')) return <Upload className="w-4 h-4 text-purple-500" />;
    if (action.startsWith('document.deleted')) return <Trash2 className="w-4 h-4 text-red-500" />;
    if (action.startsWith('vacation.')) return <Calendar className="w-4 h-4 text-amber-500" />;
    if (action.startsWith('tenant.')) return <Building2 className="w-4 h-4 text-indigo-500" />;
    return <FileText className="w-4 h-4 text-gray-500" />;
};

// Map action to badge color
const getCategoryBadge = (category: string) => {
    const styles: Record<string, string> = {
        user: "bg-blue-100 text-blue-800",
        document: "bg-purple-100 text-purple-800",
        vacation: "bg-amber-100 text-amber-800",
        tenant: "bg-indigo-100 text-indigo-800",
        batch: "bg-cyan-100 text-cyan-800",
    };

    const labels: Record<string, string> = {
        user: "Usuario",
        document: "Documento",
        vacation: "Vacaciones",
        tenant: "Organización",
        batch: "Lote",
    };

    return (
        <Badge className={`${styles[category] || "bg-gray-100 text-gray-800"} hover:${styles[category] || "bg-gray-100"}`}>
            {labels[category] || "Sistema"}
        </Badge>
    );
};

// Action descriptions
const actionDescriptions: Record<string, string> = {
    'user.login': 'Inició sesión',
    'user.logout': 'Cerró sesión',
    'user.login_failed': 'Intento de login fallido',
    'user.created': 'Usuario creado',
    'user.updated': 'Usuario actualizado',
    'user.deleted': 'Usuario eliminado',
    'user.password_changed': 'Cambió contraseña',
    'document.uploaded': 'Documento cargado',
    'document.viewed': 'Documento visualizado',
    'document.downloaded': 'Documento descargado',
    'document.signed': 'Documento firmado',
    'document.deleted': 'Documento eliminado',
    'batch.created': 'Lote de documentos creado',
    'batch.completed': 'Lote completado',
    'vacation.requested': 'Solicitó vacaciones',
    'vacation.approved': 'Vacaciones aprobadas',
    'vacation.rejected': 'Vacaciones rechazadas',
    'vacation.confirmed': 'Vacaciones confirmadas',
    'vacation.cancelled': 'Vacaciones canceladas',
    'tenant.created': 'Organización creada',
    'tenant.updated': 'Organización actualizada',
    'tenant.deleted': 'Organización eliminada',
};

export function AuditLogsPage() {
    const { currentTenant, user } = useAuthStore();
    const isRoot = user?.role === 'root';

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            search: '',
            action: 'all',
            category: 'all',
            tenant_id: '',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 20,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

    // Get tenant ID - for root use selected, for admin use currentTenant
    const tenantId = isRoot
        ? (filters.tenant_id ? Number(filters.tenant_id) : undefined)
        : (currentTenant?.id ? Number(currentTenant.id) : undefined);

    // State
    const [logs, setLogs] = useState<AuditLog[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [totalPages, setTotalPages] = useState(0);
    const [totalItems, setTotalItems] = useState(0);
    const [isExporting, setIsExporting] = useState(false);

    // Debounce search
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

    // Fetch logs
    const fetchLogs = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const reportFilters: ReportFilters = {
                tenant_id: tenantId,
                page: filters.page,
                per_page: filters.per_page,
                search: filters.search || undefined,
                action: filters.action && filters.action !== 'all' ? filters.action : undefined,
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
            };

            const response = await reportsRepository.getAuditLogs(reportFilters);
            setLogs(response.data);
            setTotalPages(response.meta.last_page);
            setTotalItems(response.meta.total);
        } catch (err) {
            setError(err instanceof Error ? err.message : "Error al cargar logs");
        } finally {
            setIsLoading(false);
        }
    }, [tenantId, filters.page, filters.per_page, filters.search, filters.action, filters.date_from, filters.date_to]);

    useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    const handleTenantChange = (id: string | null) => {
        setFilters({ tenant_id: id || '', page: 1 });
        if (!id) setSelectedTenant(null);
    };

    const handleActionChange = (value: string) => {
        setFilters({ action: value, page: 1 });
    };

    const handleCategoryChange = (value: string) => {
        setFilters({ category: value, page: 1 });
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
    };

    const handlePerPageChange = (perPage: number) => {
        setFilters({ per_page: perPage, page: 1 });
    };

    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
            page: 1,
        });
    };

    // Export
    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportAudit({
                tenant_id: tenantId,
                search: filters.search || undefined,
                action: filters.action && filters.action !== 'all' ? filters.action : undefined,
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
            });
            const filename = `auditoria_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
        } catch (err) {
            setError(err instanceof Error ? err.message : "Error al exportar");
        } finally {
            setIsExporting(false);
        }
    };

    // Clear filters
    const clearFilters = () => {
        setSearchInput('');
        setSelectedTenant(null);
        resetFilters();
    };


    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1>Registro de Actividad</h1>
                    <p className="text-[#64748B]">
                        Historial de acciones realizadas en el sistema
                    </p>
                </div>
                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        className="gap-2"
                        onClick={fetchLogs}
                        disabled={isLoading}
                    >
                        <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                        Actualizar
                    </Button>
                    <Button
                        variant="outline"
                        className="gap-2"
                        onClick={handleExport}
                        disabled={isExporting}
                    >
                        <Download className="w-4 h-4" />
                        Exportar Excel
                    </Button>
                </div>
            </div>

            {/* Error message */}
            {error && (
                <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {error}
                </div>
            )}

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap gap-3 items-end">
                        {/* Date Range Picker */}
                        <div className="min-w-[200px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Rango de fechas
                            </label>
                            <DateRangePicker
                                initialDateFrom={dateRange?.from}
                                initialDateTo={dateRange?.to}
                                onUpdate={handleDateRangeChange}
                                showCompare={false}
                                align="start"
                                compact
                            />
                        </div>

                        {/* Tenant Filter (only for root) */}
                        {isRoot && (
                            <div className="min-w-[180px]">
                                <label className="text-xs font-medium mb-1 block text-gray-600">
                                    Empresa
                                </label>
                                <TenantAutocompleteSelector
                                    value={filters.tenant_id || null}
                                    onChange={handleTenantChange}
                                    selectedTenant={selectedTenant}
                                    placeholder="Todas"
                                />
                            </div>
                        )}

                        {/* Category Filter */}
                        <div className="min-w-[140px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Categoría
                            </label>
                            <Select value={filters.category} onValueChange={handleCategoryChange}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem value="user">Usuarios</SelectItem>
                                    <SelectItem value="document">Documentos</SelectItem>
                                    <SelectItem value="vacation">Vacaciones</SelectItem>
                                    <SelectItem value="tenant">Organizaciones</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Action Filter */}
                        <div className="min-w-[160px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Acción
                            </label>
                            <Select value={filters.action} onValueChange={handleActionChange}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas las acciones</SelectItem>
                                    <SelectItem value="user.login">Inicio de sesión</SelectItem>
                                    <SelectItem value="user.logout">Cierre de sesión</SelectItem>
                                    <SelectItem value="user.login_failed">Login fallido</SelectItem>
                                    <SelectItem value="document.signed">Firma de documento</SelectItem>
                                    <SelectItem value="document.viewed">Visualización</SelectItem>
                                    <SelectItem value="vacation.approved">Vacaciones aprobadas</SelectItem>
                                    <SelectItem value="vacation.rejected">Vacaciones rechazadas</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Search */}
                        <div className="flex-1 min-w-[200px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Buscar usuario
                            </label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                <Input
                                    placeholder="Nombre o email..."
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    className="pl-8 h-9"
                                />
                            </div>
                        </div>

                        {/* Limpiar filtros */}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearFilters}
                            className="h-9 whitespace-nowrap"
                        >
                            <Filter className="w-3.5 h-3.5 mr-1.5" />
                            Limpiar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Results Summary */}
            <div className="text-sm text-gray-500">
                {totalItems > 0 ? (
                    <>Mostrando {((filters.page - 1) * filters.per_page) + 1} - {Math.min(filters.page * filters.per_page, totalItems)} de {totalItems} registros</>
                ) : (
                    <>No hay registros</>
                )}
            </div>

            {/* Logs Table */}
            <Card>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="p-6 space-y-4">
                            {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                                <div key={i} className="flex items-center gap-4">
                                    <Skeleton className="h-8 w-8 rounded-full" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-4 w-1/3" />
                                        <Skeleton className="h-3 w-1/2" />
                                    </div>
                                    <Skeleton className="h-6 w-20" />
                                </div>
                            ))}
                        </div>
                    ) : logs.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12"></TableHead>
                                    <TableHead>Usuario</TableHead>
                                    {isRoot && <TableHead>Empresa</TableHead>}
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Detalle</TableHead>
                                    <TableHead>IP</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Categoría</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.map((log) => {
                                    const category = log.action?.split('.')[0] || 'other';
                                    return (
                                        <TableRow key={log.id}>
                                            <TableCell>
                                                {getActionIcon(log.action)}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {log.user?.name || 'Sistema'}
                                                {log.user?.email && (
                                                    <span className="block text-xs text-gray-500">{log.user.email}</span>
                                                )}
                                            </TableCell>
                                            {isRoot && (
                                                <TableCell className="text-gray-700">
                                                    {log.tenant?.name || '-'}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                {actionDescriptions[log.action] || log.action}
                                            </TableCell>
                                            <TableCell className="text-gray-500 text-sm">
                                                {log.entity_type && log.entity_id && (
                                                    <span>{log.entity_type} #{log.entity_id}</span>
                                                )}
                                                {!log.entity_type && '-'}
                                            </TableCell>
                                            <TableCell className="text-gray-500 text-sm font-mono">
                                                {log.ip_address || '-'}
                                            </TableCell>
                                            <TableCell className="text-gray-500 text-sm">
                                                {log.created_at ? new Date(log.created_at).toLocaleString('es-PE') : '-'}
                                            </TableCell>
                                            <TableCell>
                                                {getCategoryBadge(category)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    ) : (
                        <div className="py-12 text-center text-gray-500">
                            <FileText className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                            <p className="font-medium">No hay registros de actividad</p>
                            <p className="text-sm mt-1">Las acciones del sistema aparecerán aquí cuando los usuarios realicen operaciones</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Pagination */}
            {totalPages > 0 && (
                <PaginationControls
                    currentPage={filters.page}
                    totalPages={totalPages}
                    total={totalItems}
                    perPage={filters.per_page}
                    onPageChange={handlePageChange}
                    onPerPageChange={handlePerPageChange}
                    disabled={isLoading}
                    showPerPageSelector={true}
                />
            )}
        </div>
    );
}

export default AuditLogsPage;
