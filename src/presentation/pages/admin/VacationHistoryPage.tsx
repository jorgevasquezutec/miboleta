import { useState, useEffect, useRef } from "react";
import { format, parse } from "date-fns";
import {
    History,
    Loader2,
    AlertCircle,
    Calendar,
    Users,
    RefreshCw,
    Search,
    Filter,
    CheckCircle,
    XCircle,
    Clock,
    Download,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import { Input } from "@/presentation/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TablePagination,
} from "@/presentation/components/ui/table";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useAuthStore } from "@/presentation/stores";
import { useUrlFilters } from "@/presentation/hooks";
import { VacationStatusBadge } from "@/presentation/components/features/vacations";
import { formatDate } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { TenantAutocompleteSelector } from "@/presentation/components/shared/TenantAutocompleteSelector";
import { Tenant } from "@/core/domain/entities/Tenant";
import { toast } from "sonner";

// Status badge helper component
function TakenBadge({ wasTaken }: { wasTaken: boolean | null | undefined }) {
    if (wasTaken === null || wasTaken === undefined) {
        return (
            <Badge variant="outline" className="text-gray-500 border-gray-300 text-xs">
                <Clock className="w-3 h-3 mr-1" />
                Pendiente
            </Badge>
        );
    }
    if (wasTaken) {
        return (
            <Badge className="bg-green-100 text-green-700 border-green-200 text-xs">
                <CheckCircle className="w-3 h-3 mr-1" />
                Sí
            </Badge>
        );
    }
    return (
        <Badge className="bg-orange-100 text-orange-700 border-orange-200 text-xs">
            <XCircle className="w-3 h-3 mr-1" />
            No
        </Badge>
    );
}

export function VacationHistoryPage() {
    const {
        historyRequests,
        historyTotal,
        historyTotalPages,
        fetchHistoryRequests,
        isLoading,
        error,
    } = useVacationsStore();

    const { currentTenant, user } = useAuthStore();
    const isRoot = user?.role === 'root';

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            status: 'all',
            search: '',
            tenant_id: '',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 10,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [selectedTenant, setSelectedTenant] = useState<Tenant | null>(null);
    const [isExporting, setIsExporting] = useState(false);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Get tenant ID - for root use selected, for admin use currentTenant
    const tenantId = isRoot
        ? (filters.tenant_id ? Number(filters.tenant_id) : undefined)
        : (currentTenant?.id ? Number(currentTenant.id) : undefined);



    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

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

    // Fetch data when filters change
    useEffect(() => {
        fetchHistoryRequests({
            page: filters.page,
            perPage: filters.per_page,
            status: filters.status !== 'all' ? filters.status : undefined,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
            search: filters.search || undefined,
            tenantId: tenantId,
        });
    }, [filters.status, filters.page, filters.per_page, filters.date_from, filters.date_to, filters.search, filters.tenant_id, tenantId, fetchHistoryRequests]);

    const handleRefresh = () => {
        fetchHistoryRequests({
            page: filters.page,
            perPage: filters.per_page,
            status: filters.status !== 'all' ? filters.status : undefined,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
            search: filters.search || undefined,
            tenantId: tenantId,
        });
    };

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handleTenantChange = (id: string | null) => {
        setFilters({ tenant_id: id || '', page: 1 });
        if (!id) setSelectedTenant(null);
    };



    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
            page: 1,
        });
    };

    const handlePageChange = (newPage: number) => {
        if (newPage >= 1 && newPage <= historyTotalPages) {
            setFilters({ page: newPage });
        }
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportVacations({
                tenant_id: tenantId,
                status: filters.status !== 'all' ? filters.status : undefined,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
                search: filters.search || undefined,
            });
            const filename = `vacaciones_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error: any) {
            // Try to get message from JSON response or error object
            const errorMessage = error?.response?.data?.message
                || error?.response?.data?.error
                || error?.message
                || 'Error al exportar vacaciones';
            toast.error(errorMessage);
        } finally {
            setIsExporting(false);
        }
    };

    // Filter by search term (client-side for name/email)
    const filteredRequests = historyRequests.filter((request) => {
        if (!filters.search) return true;
        const search = filters.search.toLowerCase();
        const userName = request.user?.fullName?.toLowerCase() || "";
        const userEmail = request.user?.email?.toLowerCase() || "";
        return userName.includes(search) || userEmail.includes(search);
    });

    // Stats
    const approvedCount = historyRequests.filter((r) => r.status === "approved").length;
    const takenCount = historyRequests.filter((r) => r.wasTaken === true).length;

    if (error) {
        return (
            <div className="flex items-center justify-center h-96">
                <Card className="max-w-md">
                    <CardContent className="p-6 text-center">
                        <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Error</h3>
                        <p className="text-gray-600">{error}</p>
                        <Button className="mt-4" onClick={handleRefresh}>
                            Reintentar
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <History className="w-7 h-7 text-blue-600" />
                        Histórico de Vacaciones
                    </h1>
                    <p className="text-gray-600 mt-1">
                        Todas las vacaciones de {currentTenant?.name || "la empresa"}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        onClick={handleExport}
                        disabled={isExporting}
                    >
                        {isExporting ? (
                            <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                        ) : (
                            <Download className="w-4 h-4 mr-2" />
                        )}
                        Exportar
                    </Button>
                    <Button variant="outline" onClick={handleRefresh} disabled={isLoading}>
                        <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? "animate-spin" : ""}`} />
                        Actualizar
                    </Button>
                </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-blue-100">
                            <Calendar className="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Total Solicitudes</p>
                            <p className="text-2xl font-bold text-blue-600">{historyTotal}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-green-100">
                            <Users className="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Aprobadas</p>
                            <p className="text-2xl font-bold text-green-600">{approvedCount}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-purple-100">
                            <Calendar className="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Tomadas</p>
                            <p className="text-2xl font-bold text-purple-600">{takenCount}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

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

                        {/* Status Filter */}
                        <div className="min-w-[160px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Estado
                            </label>
                            <Select value={filters.status} onValueChange={handleStatusChange}>
                                <SelectTrigger className="h-9">
                                    <Filter className="w-3.5 h-3.5 mr-1.5" />
                                    <SelectValue placeholder="Estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="pending">Pendientes</SelectItem>
                                    <SelectItem value="approved">Aprobadas</SelectItem>
                                    <SelectItem value="rejected">Rechazadas</SelectItem>
                                    <SelectItem value="cancelled">Canceladas</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Search */}
                        <div className="flex-1 min-w-[200px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Buscar empleado
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
                            onClick={() => {
                                setSearchInput('');
                                setSelectedTenant(null);
                                resetFilters();
                            }}
                            className="h-9 whitespace-nowrap"
                        >
                            <Filter className="w-3.5 h-3.5 mr-1.5" />
                            Limpiar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Results Table */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="text-lg flex items-center gap-2">
                        Solicitudes
                        {historyTotal > 0 && (
                            <Badge className="bg-blue-100 text-blue-800 border-blue-200">
                                {historyTotal} total
                            </Badge>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                        </div>
                    ) : filteredRequests.length === 0 ? (
                        <div className="text-center py-12">
                            <Calendar className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                No hay solicitudes
                            </h3>
                            <p className="text-gray-600">
                                {filters.search || filters.status !== 'all' || filters.date_from
                                    ? "No se encontraron solicitudes con los filtros seleccionados"
                                    : "Aún no hay historial de vacaciones"}
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Empleado</TableHead>
                                    <TableHead>Fechas</TableHead>
                                    <TableHead className="text-center">Días</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Tomada</TableHead>
                                    <TableHead>Solicitado</TableHead>
                                    <TableHead>Aprobado por</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredRequests.map((request) => (
                                    <TableRow key={request.id}>
                                        {/* Empleado */}
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                                    {request.user?.fullName?.charAt(0) || "?"}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-900 text-sm">
                                                        {request.user?.fullName || "Usuario desconocido"}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {request.user?.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>

                                        {/* Fechas */}
                                        <TableCell>
                                            <span className="text-sm text-gray-700">{request.dateRange}</span>
                                        </TableCell>

                                        {/* Días */}
                                        <TableCell className="text-center">
                                            <span className="font-semibold text-gray-900">{request.daysRequested}</span>
                                        </TableCell>

                                        {/* Estado */}
                                        <TableCell>
                                            <VacationStatusBadge
                                                status={request.status}
                                                wasTaken={request.wasTaken}
                                                showTakenBadge={false}
                                            />
                                        </TableCell>

                                        {/* Tomada */}
                                        <TableCell>
                                            {request.status === "approved" ? (
                                                <TakenBadge wasTaken={request.wasTaken ?? null} />
                                            ) : (
                                                <span className="text-gray-400 text-xs">-</span>
                                            )}
                                        </TableCell>

                                        {/* Solicitado */}
                                        <TableCell>
                                            <span className="text-sm text-gray-600">
                                                {formatDate(request.createdAt)}
                                            </span>
                                        </TableCell>

                                        {/* Aprobado por */}
                                        <TableCell>
                                            {request.approvedByUser ? (
                                                <div>
                                                    <p className="text-sm text-gray-700">
                                                        {request.approvedByUser.fullName}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {request.approvedAt && formatDate(request.approvedAt)}
                                                    </p>
                                                </div>
                                            ) : request.rejectedByUser ? (
                                                <div>
                                                    <p className="text-sm text-red-600">
                                                        Rechazado por {request.rejectedByUser.fullName}
                                                    </p>
                                                </div>
                                            ) : (
                                                <span className="text-gray-400 text-xs">-</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    {/* Pagination */}
                    {historyTotal > 0 && (
                        <div className="mt-4 pt-4 border-t">
                            <TablePagination
                                currentPage={filters.page}
                                totalPages={historyTotalPages || 1}
                                totalItems={historyTotal}
                                pageSize={filters.per_page}
                                pageSizeOptions={[10, 25, 50, 100]}
                                onPageChange={handlePageChange}
                                onPageSizeChange={(size) => {
                                    setFilters({ per_page: size, page: 1 });
                                }}
                                showPageSizeSelector={true}
                                showPageInfo={true}
                            />
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default VacationHistoryPage;
