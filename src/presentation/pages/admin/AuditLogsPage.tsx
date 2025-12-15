import { useEffect, useState, useCallback } from "react";
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
    ChevronLeft,
    ChevronRight,
    X,
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
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
import { useAuthStore } from "@/presentation/stores/authStore";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
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

    // State for root users to select a tenant
    const [selectedTenantId, setSelectedTenantId] = useState<string | null>(null);
    const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);

    // Get tenant ID - for root use selected, for admin use currentTenant
    const tenantId = isRoot
        ? (selectedTenantId ? Number(selectedTenantId) : undefined)
        : (currentTenant?.id ? Number(currentTenant.id) : undefined);

    // State
    const [logs, setLogs] = useState<AuditLog[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(0);
    const [totalItems, setTotalItems] = useState(0);
    const [isExporting, setIsExporting] = useState(false);

    // Filters
    const [searchTerm, setSearchTerm] = useState("");
    const [actionFilter, setActionFilter] = useState<string>("");
    const [categoryFilter, setCategoryFilter] = useState<string>("");

    // Fetch logs
    const fetchLogs = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const filters: ReportFilters = {
                tenant_id: tenantId,
                page: currentPage,
                per_page: 20,
                search: searchTerm || undefined,
                action: actionFilter && actionFilter !== 'all' ? actionFilter : undefined,
            };

            const response = await reportsRepository.getAuditLogs(filters);
            setLogs(response.data);
            setTotalPages(response.meta.last_page);
            setTotalItems(response.meta.total);
        } catch (err) {
            setError(err instanceof Error ? err.message : "Error al cargar logs");
        } finally {
            setIsLoading(false);
        }
    }, [tenantId, currentPage, searchTerm, actionFilter]);

    useEffect(() => {
        fetchLogs();
    }, [fetchLogs]);

    // Reset page when filters change
    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm, actionFilter, categoryFilter, selectedTenantId]);

    // Export
    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportAudit({
                tenant_id: tenantId,
                action: actionFilter && actionFilter !== 'all' ? actionFilter : undefined,
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
        setSearchTerm("");
        setActionFilter("all");
        setCategoryFilter("all");
        setCurrentPage(1);
    };

    const hasFilters = searchTerm || (actionFilter && actionFilter !== 'all') || (categoryFilter && categoryFilter !== 'all');

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
                <CardHeader className="pb-3">
                    <CardTitle className="text-base font-medium flex items-center gap-2">
                        <Filter className="w-4 h-4" />
                        Filtros
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Tenant Filter (only for root) */}
                        {isRoot && (
                            <div>
                                <TenantAutocompleteSelector
                                    value={selectedTenantId}
                                    onChange={(id) => {
                                        setSelectedTenantId(id);
                                        if (!id) setSelectedTenant(null);
                                    }}
                                    selectedTenant={selectedTenant}
                                    placeholder="Todas las organizaciones"
                                />
                            </div>
                        )}

                        {/* Search */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <Input
                                placeholder="Buscar por usuario..."
                                className="pl-9"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>

                        {/* Category Filter */}
                        <Select value={categoryFilter || "all"} onValueChange={setCategoryFilter}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todas las categorías" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas las categorías</SelectItem>
                                <SelectItem value="user">Usuarios</SelectItem>
                                <SelectItem value="document">Documentos</SelectItem>
                                <SelectItem value="vacation">Vacaciones</SelectItem>
                                <SelectItem value="tenant">Organizaciones</SelectItem>
                            </SelectContent>
                        </Select>

                        {/* Action Filter */}
                        <Select value={actionFilter || "all"} onValueChange={setActionFilter}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todas las acciones" />
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

                        {/* Clear Filters */}
                        {hasFilters && (
                            <Button variant="ghost" onClick={clearFilters} className="gap-2">
                                <X className="w-4 h-4" />
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Results Summary */}
            <div className="text-sm text-gray-500">
                {totalItems > 0 ? (
                    <>Mostrando {((currentPage - 1) * 20) + 1} - {Math.min(currentPage * 20, totalItems)} de {totalItems} registros</>
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
            {totalPages > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                        disabled={currentPage === 1 || isLoading}
                    >
                        <ChevronLeft className="w-4 h-4" />
                        Anterior
                    </Button>

                    <div className="flex items-center gap-1">
                        {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                            let pageNum: number;
                            if (totalPages <= 5) {
                                pageNum = i + 1;
                            } else if (currentPage <= 3) {
                                pageNum = i + 1;
                            } else if (currentPage >= totalPages - 2) {
                                pageNum = totalPages - 4 + i;
                            } else {
                                pageNum = currentPage - 2 + i;
                            }

                            return (
                                <Button
                                    key={pageNum}
                                    variant={currentPage === pageNum ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => setCurrentPage(pageNum)}
                                    disabled={isLoading}
                                    className="w-10"
                                >
                                    {pageNum}
                                </Button>
                            );
                        })}
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                        disabled={currentPage === totalPages || isLoading}
                    >
                        Siguiente
                        <ChevronRight className="w-4 h-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}

export default AuditLogsPage;
