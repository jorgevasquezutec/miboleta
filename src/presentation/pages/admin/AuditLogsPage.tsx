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
    Shield,
    Settings,
    KeyRound,
    UserCog,
    Users,
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
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { AuditLog, ReportFilters } from "@/core/domain/entities";

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
    if (action.startsWith('role.')) return <Shield className="w-4 h-4 text-rose-500" />;
    if (action.startsWith('platform.')) return <Settings className="w-4 h-4 text-slate-500" />;
    if (action.startsWith('signature.')) return <FileSignature className="w-4 h-4 text-emerald-500" />;
    if (action.startsWith('user_batch.')) return <Users className="w-4 h-4 text-cyan-600" />;
    if (action.startsWith('profile.')) return <UserCog className="w-4 h-4 text-blue-500" />;
    if (action.includes('password') || action === 'user.email_changed') return <KeyRound className="w-4 h-4 text-orange-500" />;
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
        role: "bg-rose-100 text-rose-800",
        platform: "bg-slate-100 text-slate-800",
        signature: "bg-emerald-100 text-emerald-800",
        user_batch: "bg-cyan-100 text-cyan-800",
        profile: "bg-blue-100 text-blue-800",
    };

    const labels: Record<string, string> = {
        user: "Usuario",
        document: "Documento",
        vacation: "Vacaciones",
        tenant: "Organización",
        batch: "Lote",
        role: "Roles",
        platform: "Plataforma",
        signature: "Firma",
        user_batch: "Carga masiva",
        profile: "Perfil",
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
    'user.password_reset': 'Restableció contraseña',
    'user.password_reset_requested': 'Solicitó restablecer contraseña',
    'user.email_changed': 'Cambió el correo electrónico',
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
    'role.assigned': 'Asignó roles a un usuario',
    'platform.settings_updated': 'Actualizó la configuración de la plataforma',
    'signature.settings_updated': 'Actualizó la configuración de firma',
    'signature.certificate_uploaded': 'Cargó el certificado de firma',
    'signature.certificate_deleted': 'Eliminó el certificado de firma',
    'signature.terms_accepted': 'Aceptó los términos de firma',
    'user_batch.created': 'Creó una carga masiva de usuarios',
    'user_batch.completed': 'Completó una carga masiva de usuarios',
    'profile.updated': 'Actualizó su perfil',
    'profile.data_change_requested': 'Solicitó cambio de datos',
};

export function AuditLogsPage() {
    useDocumentTitle('Registro de Actividad');
    const { user, currentTenant } = useAuthStore();
    const isRoot = user?.role === 'root';

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            search: '',
            action: 'all',
            category: 'all',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 20,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;


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

    // Semilla inicial del input de búsqueda desde la URL. Solo al montar
    // A PROPÓSITO: con `filters.search` en las dependencias, el valor que el
    // debounce escribe en la URL 500 ms después volvería a entrar aquí y
    // pisaría lo que el usuario haya seguido tecleando entre medias.
    useEffect(() => {
        setSearchInput(filters.search);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Fetch logs
    // El modelo AuditLog NO tiene TenantFilterScope (no respeta el header
    // X-Tenant-Ids), así que ReportsController::audit solo filtra por
    // empresa vía el query param `tenant_id` (ver getTenantId en el
    // backend). Para root, ese tenant_id sale de la empresa activa del
    // navbar (authStore.currentTenant); para no-root (admin), el backend
    // ya lo resuelve solo a partir del usuario, así que no enviamos nada.
    // El id se extrae fuera del callback (primitivo) para que la dependencia
    // sea exactamente lo que el cuerpo usa. Antes el cuerpo leía `currentTenant`
    // entero mientras las deps declaraban `currentTenant?.id`: ESLint no podía
    // verificarlo y, si el store devolviera un objeto nuevo con el mismo id, la
    // identidad de fetchLogs cambiaría sin motivo.
    const tenantIdFilter = isRoot && currentTenant ? Number(currentTenant.id) : undefined;

    const fetchLogs = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const reportFilters: ReportFilters = {
                tenant_id: tenantIdFilter,
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
    }, [filters.page, filters.per_page, filters.search, filters.action, filters.date_from, filters.date_to, tenantIdFilter]);

    // Reacciona tanto al filtro global de tenant (useTenantAwareEffect)
    // como al cambio de empresa activa para root (currentTenant?.id ya
    // forma parte de la identidad de fetchLogs vía su dependencia).
    useTenantAwareEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

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
                tenant_id: isRoot ? (currentTenant ? Number(currentTenant.id) : undefined) : undefined,
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
        resetFilters();
    };


    return (
        <div className="space-y-4 sm:space-y-6 overflow-x-hidden">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold">Registro de Actividad</h1>
                    <p className="text-[#64748B] text-sm sm:text-base">
                        Historial de acciones realizadas en el sistema
                    </p>
                </div>
                <div className="flex gap-2 sm:gap-3">
                    <Button
                        variant="outline"
                        className="h-9 sm:h-10 px-3 sm:px-4 gap-2"
                        onClick={fetchLogs}
                        disabled={isLoading}
                    >
                        <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                        <span className="hidden xs:inline">Actualizar</span>
                    </Button>
                    <Button
                        variant="outline"
                        className="h-9 sm:h-10 px-3 sm:px-4 gap-2"
                        onClick={handleExport}
                        disabled={isExporting}
                    >
                        <Download className="w-4 h-4" />
                        <span className="hidden xs:inline">Exportar</span>
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
                <CardContent className="p-3 sm:p-4">
                    <div className="grid grid-cols-1 xs:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                        {/* Date Range Picker */}
                        <div className="xs:col-span-2 md:col-span-1">
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

                        {/* Nota: sin dropdown local de empresa para root. El navbar
                            (TenantSwitcher) es su único control de empresa; ver
                            fetchLogs/handleExport, que derivan tenant_id de
                            authStore.currentTenant. */}

                        {/* Category Filter */}
                        <div>
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
                                    <SelectItem value="role">Roles</SelectItem>
                                    <SelectItem value="platform">Plataforma</SelectItem>
                                    <SelectItem value="signature">Firma</SelectItem>
                                    <SelectItem value="user_batch">Carga masiva</SelectItem>
                                    <SelectItem value="profile">Perfil</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Action Filter */}
                        <div>
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
                                    <SelectItem value="document.downloaded">Descarga</SelectItem>
                                    <SelectItem value="vacation.approved">Vacaciones aprobadas</SelectItem>
                                    <SelectItem value="vacation.rejected">Vacaciones rechazadas</SelectItem>
                                    <SelectItem value="role.assigned">Asignación de roles</SelectItem>
                                    <SelectItem value="platform.settings_updated">Configuración de plataforma</SelectItem>
                                    <SelectItem value="user.password_reset">Reset de contraseña</SelectItem>
                                    <SelectItem value="signature.certificate_uploaded">Carga de certificado</SelectItem>
                                    <SelectItem value="user_batch.completed">Carga masiva completada</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Search */}
                        <div className="xs:col-span-2 md:col-span-1">
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
                            className="h-9 whitespace-nowrap w-full xs:w-auto"
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
                        <div className="p-4 sm:p-6 space-y-4">
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
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10 sm:w-12"></TableHead>
                                        <TableHead className="min-w-[120px]">Usuario</TableHead>
                                        {isRoot && <TableHead className="min-w-[100px] hidden sm:table-cell">Empresa</TableHead>}
                                        <TableHead className="min-w-[100px]">Acción</TableHead>
                                        <TableHead className="min-w-[80px] hidden md:table-cell">Detalle</TableHead>
                                        <TableHead className="min-w-[100px] hidden lg:table-cell">IP</TableHead>
                                        <TableHead className="min-w-[140px]">Fecha</TableHead>
                                        <TableHead className="min-w-[80px] hidden sm:table-cell">Categoría</TableHead>
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
                                                    <span className="truncate max-w-[120px] block">{log.user?.name || 'Sistema'}</span>
                                                    {log.user?.email && (
                                                        <span className="block text-xs text-gray-500 truncate max-w-[120px]">{log.user.email}</span>
                                                    )}
                                                </TableCell>
                                                {isRoot && (
                                                    <TableCell className="text-gray-700 hidden sm:table-cell">
                                                        {log.tenant?.name || '-'}
                                                    </TableCell>
                                                )}
                                                <TableCell className="text-sm">
                                                    {actionDescriptions[log.action] || log.action}
                                                </TableCell>
                                                <TableCell className="text-gray-500 text-sm hidden md:table-cell">
                                                    {log.entity_type && log.entity_id && (
                                                        <span>{log.entity_type} #{log.entity_id}</span>
                                                    )}
                                                    {!log.entity_type && '-'}
                                                </TableCell>
                                                <TableCell className="text-gray-500 text-sm font-mono hidden lg:table-cell">
                                                    {log.ip_address || '-'}
                                                </TableCell>
                                                <TableCell className="text-gray-500 text-xs sm:text-sm">
                                                    {log.created_at ? new Date(log.created_at).toLocaleString('es-PE') : '-'}
                                                </TableCell>
                                                <TableCell className="hidden sm:table-cell">
                                                    {getCategoryBadge(category)}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="py-8 sm:py-12 text-center text-gray-500 px-4">
                            <FileText className="w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-3 text-gray-300" />
                            <p className="font-medium text-sm sm:text-base">No hay registros de actividad</p>
                            <p className="text-xs sm:text-sm mt-1">Las acciones del sistema aparecerán aquí</p>
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
