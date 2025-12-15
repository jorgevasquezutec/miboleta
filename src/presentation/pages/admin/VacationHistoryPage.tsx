import { useState, useEffect, useRef } from "react";
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
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useAuthStore } from "@/presentation/stores";
import { useUrlFilters } from "@/presentation/hooks";
import { VacationStatusBadge } from "@/presentation/components/features/vacations";
import { formatDate } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
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

    const { currentTenant } = useAuthStore();

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            status: 'all',
            year: 'all',
            search: '',
            page: 1,
            per_page: 10,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [isExporting, setIsExporting] = useState(false);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Get available years for filter (current year and 4 previous)
    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

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
            year: filters.year !== 'all' ? parseInt(filters.year) : undefined,
        });
    }, [filters.status, filters.year, filters.page, filters.per_page, currentTenant, fetchHistoryRequests]);

    const handleRefresh = () => {
        fetchHistoryRequests({
            page: filters.page,
            perPage: filters.per_page,
            status: filters.status !== 'all' ? filters.status : undefined,
            year: filters.year !== 'all' ? parseInt(filters.year) : undefined,
        });
    };

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handleYearChange = (value: string) => {
        setFilters({ year: value, page: 1 });
    };

    const handlePageChange = (newPage: number) => {
        if (newPage >= 1 && newPage <= historyTotalPages) {
            setFilters({ page: newPage });
        }
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const tenantId = currentTenant?.id ? Number(currentTenant.id) : undefined;
            const blob = await reportsRepository.exportVacations({
                tenant_id: tenantId,
                status: filters.status !== 'all' ? filters.status : undefined,
                year: filters.year !== 'all' ? parseInt(filters.year) : undefined,
            });
            const filename = `vacaciones_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            toast.error('Error al exportar vacaciones');
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
                    <div className="flex flex-col sm:flex-row gap-4">
                        {/* Search */}
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <Input
                                placeholder="Buscar por nombre o email..."
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Status Filter */}
                        <Select value={filters.status} onValueChange={handleStatusChange}>
                            <SelectTrigger className="w-full sm:w-[180px]">
                                <Filter className="w-4 h-4 mr-2" />
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los estados</SelectItem>
                                <SelectItem value="pending">Pendientes</SelectItem>
                                <SelectItem value="approved">Aprobadas</SelectItem>
                                <SelectItem value="rejected">Rechazadas</SelectItem>
                                <SelectItem value="cancelled">Canceladas</SelectItem>
                            </SelectContent>
                        </Select>

                        {/* Year Filter */}
                        <Select value={filters.year} onValueChange={handleYearChange}>
                            <SelectTrigger className="w-full sm:w-[140px]">
                                <Calendar className="w-4 h-4 mr-2" />
                                <SelectValue placeholder="Año" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los años</SelectItem>
                                {years.map((year) => (
                                    <SelectItem key={year} value={year.toString()}>
                                        {year}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                                {filters.search || filters.status !== 'all' || filters.year !== 'all'
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
