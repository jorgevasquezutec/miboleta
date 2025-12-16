import { useState, useEffect } from "react";
import { format, parse } from "date-fns";
import {
    ClipboardCheck,
    Loader2,
    AlertCircle,
    Calendar,
    CheckCircle,
    Clock,
    RefreshCw,
    Download,
    Filter,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { VacationRequestCard, VacationRejectModal } from "@/presentation/components/features/vacations";
import { VacationRequest } from "@/core/domain/entities";
import { useAuthStore } from "@/presentation/stores";
import { useUrlFilters } from "@/presentation/hooks";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";

export function VacationApprovalsPage() {
    const {
        pendingApprovals,
        pendingApprovalsCount,
        fetchPendingApprovals,
        approveRequest,
        rejectRequest,
        isLoading,
        error,
    } = useVacationsStore();

    const { currentTenant } = useAuthStore();

    // URL-synced filters for date range
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            date_from: '',
            date_to: '',
        }
    });

    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<VacationRequest | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

    useEffect(() => {
        fetchPendingApprovals();
    }, [fetchPendingApprovals]);

    const handleApprove = async (id: number) => {
        setProcessingId(id);
        try {
            await approveRequest(id);
            toast.success("Solicitud aprobada correctamente. Se ha notificado al empleado.");
        } catch {
            toast.error("No se pudo aprobar la solicitud");
        } finally {
            setProcessingId(null);
        }
    };

    const handleRejectClick = (id: number) => {
        const request = pendingApprovals.find((r) => r.id === id);
        if (request) {
            setSelectedRequest(request);
            setRejectModalOpen(true);
        }
    };

    const handleRejectConfirm = async (id: number, reason: string) => {
        setProcessingId(id);
        try {
            await rejectRequest(id, reason);
            toast.success("Solicitud rechazada. Se ha notificado al empleado.");
            setRejectModalOpen(false);
            setSelectedRequest(null);
        } catch {
            toast.error("No se pudo rechazar la solicitud");
        } finally {
            setProcessingId(null);
        }
    };

    const handleRefresh = () => {
        fetchPendingApprovals();
    };

    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
        });
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const tenantId = currentTenant?.id ? Number(currentTenant.id) : undefined;
            const blob = await reportsRepository.exportVacations({
                tenant_id: tenantId,
                status: 'pending',
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
            });
            const filename = `vacaciones_pendientes_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            toast.error('Error al exportar vacaciones');
        } finally {
            setIsExporting(false);
        }
    };

    // Filter pending approvals by date range
    const filteredApprovals = pendingApprovals.filter((request) => {
        if (!filters.date_from) return true;
        const requestDate = new Date(request.startDate);
        const fromDate = parse(filters.date_from, 'yyyy-MM-dd', new Date());
        const toDate = filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined;

        if (toDate) {
            return requestDate >= fromDate && requestDate <= toDate;
        }
        return requestDate >= fromDate;
    });

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
                        <ClipboardCheck className="w-7 h-7 text-blue-600" />
                        Aprobar Vacaciones
                    </h1>
                    <p className="text-gray-600 mt-1">
                        Gestiona las solicitudes de vacaciones de tu equipo
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        onClick={handleExport}
                        disabled={isExporting || filteredApprovals.length === 0}
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

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col sm:flex-row gap-4 items-end">
                        <div className="flex-1">
                            <label className="text-sm font-medium mb-2 block">Filtrar por rango de fechas</label>
                            <DateRangePicker
                                initialDateFrom={dateRange?.from}
                                initialDateTo={dateRange?.to}
                                onUpdate={handleDateRangeChange}
                                showCompare={false}
                                align="start"
                                compact
                            />
                        </div>
                        {(filters.date_from || filters.date_to) && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setFilters({ date_from: '', date_to: '' });
                                }}
                            >
                                <Filter className="w-4 h-4 mr-2" />
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-yellow-100">
                            <Clock className="w-6 h-6 text-yellow-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Pendientes de Aprobación</p>
                            <p className="text-2xl font-bold text-yellow-600">
                                {pendingApprovalsCount}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-green-100">
                            <CheckCircle className="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Aprobadas Hoy</p>
                            <p className="text-2xl font-bold text-green-600">-</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-blue-100">
                            <Calendar className="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Solicitudes Este Mes</p>
                            <p className="text-2xl font-bold text-blue-600">-</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Solicitudes Pendientes */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="text-lg flex items-center gap-2">
                        Solicitudes Pendientes
                        {filteredApprovals.length > 0 && (
                            <Badge className="bg-yellow-100 text-yellow-800 border-yellow-200">
                                {filteredApprovals.length}
                            </Badge>
                        )}
                        {filters.date_from && pendingApprovalsCount !== filteredApprovals.length && (
                            <span className="text-sm text-gray-500 font-normal">
                                (filtradas de {pendingApprovalsCount})
                            </span>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                        </div>
                    ) : filteredApprovals.length === 0 ? (
                        <div className="text-center py-12">
                            <CheckCircle className="w-12 h-12 text-green-400 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                {filters.date_from ? 'No hay solicitudes en este rango' : '¡Todo al día!'}
                            </h3>
                            <p className="text-gray-600">
                                {filters.date_from
                                    ? 'No hay solicitudes pendientes en el rango de fechas seleccionado'
                                    : 'No tienes solicitudes pendientes de aprobar'}
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {filteredApprovals.map((request) => (
                                <VacationRequestCard
                                    key={request.id}
                                    request={request}
                                    mode="approval"
                                    showActions
                                    onApprove={handleApprove}
                                    onReject={handleRejectClick}
                                    isLoading={processingId === request.id}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Modal de Rechazo */}
            <VacationRejectModal
                open={rejectModalOpen}
                onOpenChange={setRejectModalOpen}
                request={selectedRequest}
                onConfirm={handleRejectConfirm}
                isLoading={processingId !== null}
            />
        </div>
    );
}

export default VacationApprovalsPage;
