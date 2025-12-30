import { useState } from "react";
import { useNavigate } from "react-router-dom";
import {
    Calendar,
    Plus,
    Clock,
    CheckCircle,
    XCircle,
    Loader2,
    AlertCircle,
    Filter,
    CalendarDays,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import { Badge } from "@/presentation/components/ui/badge";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { VacationStatus } from "@/core/domain/entities";
import { formatDate } from "@/presentation/utils";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { toast } from "sonner";

export function VacationRequestsListPage() {
    useDocumentTitle('Mis Vacaciones');
    const navigate = useNavigate();
    const {
        vacationRequests,
        fetchVacationRequests,
        cancelVacationRequest,
        isLoading,
        error,
        total,
        totalPages,
    } = useVacationsStore();

    // URL-synced filters
    const { filters, setFilters } = useUrlFilters({
        defaultValues: {
            status: 'all',
            page: 1,
            per_page: 10,
        }
    });

    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [selectedRequestId, setSelectedRequestId] = useState<number | null>(null);

    // ✅ MIGRATED: Now reacts automatically to tenant filter changes
    useTenantAwareEffect(() => {
        fetchVacationRequests({
            status: filters.status !== 'all' ? (filters.status as VacationStatus) : undefined,
            page: filters.page,
            perPage: filters.per_page,
        });
    }, [fetchVacationRequests, filters.page, filters.per_page, filters.status]);

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
    };

    const handlePerPageChange = (perPage: number) => {
        setFilters({ per_page: perPage, page: 1 });
    };

    const handleNewRequest = () => {
        navigate("/vacations/new");
    };

    const handleCancelClick = (id: number) => {
        setSelectedRequestId(id);
        setCancelDialogOpen(true);
    };

    const handleConfirmCancel = async () => {
        if (!selectedRequestId) return;
        try {
            await cancelVacationRequest(selectedRequestId);
            toast.success("Solicitud cancelada correctamente");
            setCancelDialogOpen(false);
        } catch {
            toast.error("No se pudo cancelar la solicitud");
        }
    };

    const getStatusBadge = (status: VacationStatus) => {
        const colorMap: Record<VacationStatus, string> = {
            pending: "bg-yellow-100 text-yellow-800 border-yellow-200",
            approved: "bg-green-100 text-green-800 border-green-200",
            rejected: "bg-red-100 text-red-800 border-red-200",
            cancelled: "bg-gray-100 text-gray-800 border-gray-200",
        };

        const iconMap: Record<VacationStatus, React.ReactNode> = {
            pending: <Clock className="w-3 h-3 mr-1" />,
            approved: <CheckCircle className="w-3 h-3 mr-1" />,
            rejected: <XCircle className="w-3 h-3 mr-1" />,
            cancelled: <XCircle className="w-3 h-3 mr-1" />,
        };

        const labelMap: Record<VacationStatus, string> = {
            pending: "Pendiente",
            approved: "Aprobada",
            rejected: "Rechazada",
            cancelled: "Cancelada",
        };

        return (
            <Badge variant="outline" className={`${colorMap[status]} flex items-center`}>
                {iconMap[status]}
                {labelMap[status]}
            </Badge>
        );
    };

    const getTakenBadge = (wasTaken: boolean | null | undefined) => {
        if (wasTaken === null || wasTaken === undefined) {
            return null;
        }
        if (wasTaken) {
            return (
                <Badge variant="outline" className="bg-blue-100 text-blue-800 border-blue-200">
                    ✓ Tomada
                </Badge>
            );
        }
        return (
            <Badge variant="outline" className="bg-orange-100 text-orange-800 border-orange-200">
                ✗ No tomada
            </Badge>
        );
    };

    // Statistics
    const pendingCount = vacationRequests.filter((r) => r.status === "pending").length;
    const approvedCount = vacationRequests.filter((r) => r.status === "approved").length;

    if (error) {
        return (
            <div className="flex items-center justify-center h-96">
                <Card className="max-w-md">
                    <CardContent className="p-6 text-center">
                        <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Error</h3>
                        <p className="text-gray-600">{error}</p>
                        <Button className="mt-4" onClick={() => fetchVacationRequests()}>
                            Reintentar
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <div className="space-y-4 sm:space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <Calendar className="w-6 h-6 sm:w-7 sm:h-7 text-blue-600" />
                        Mis Vacaciones
                    </h1>
                    <p className="text-gray-600 text-sm sm:text-base mt-1">Gestiona tus solicitudes de vacaciones</p>
                </div>
                <Button
                    onClick={handleNewRequest}
                    className="h-9 sm:h-10 px-3 sm:px-4 bg-blue-600 hover:bg-blue-700 text-white w-full sm:w-auto"
                >
                    <Plus className="w-4 h-4 mr-2" />
                    Nueva Solicitud
                </Button>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-blue-100">
                            <CalendarDays className="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Total Solicitudes</p>
                            <p className="text-2xl font-bold text-gray-900">{total}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-yellow-100">
                            <Clock className="w-6 h-6 text-yellow-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Pendientes</p>
                            <p className="text-2xl font-bold text-yellow-600">{pendingCount}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-green-100">
                            <CheckCircle className="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Aprobadas</p>
                            <p className="text-2xl font-bold text-green-600">{approvedCount}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                        <div className="flex items-center gap-2">
                            <Filter className="w-4 h-4 text-gray-500" />
                            <span className="text-sm font-medium text-gray-700">Filtrar por:</span>
                        </div>
                        <Select
                            value={filters.status}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger className="w-[180px]">
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
                    </div>
                </CardContent>
            </Card>

            {/* Requests List */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Solicitudes de Vacaciones</CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                        </div>
                    ) : vacationRequests.length === 0 ? (
                        <div className="text-center py-12">
                            <Calendar className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                No tienes solicitudes de vacaciones
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Crea tu primera solicitud para empezar
                            </p>
                            <Button onClick={handleNewRequest}>
                                <Plus className="w-4 h-4 mr-2" />
                                Nueva Solicitud
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {vacationRequests.map((request) => (
                                <div
                                    key={request.id}
                                    className="border rounded-lg p-4 hover:bg-gray-50 transition-colors"
                                >
                                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                {getStatusBadge(request.status)}
                                                {getTakenBadge(request.wasTaken)}
                                            </div>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                                <div>
                                                    <span className="text-gray-500">Fechas:</span>
                                                    <p className="font-medium text-gray-900">{request.dateRange}</p>
                                                </div>
                                                <div>
                                                    <span className="text-gray-500">Días:</span>
                                                    <p className="font-medium text-gray-900">{request.durationText}</p>
                                                </div>
                                                <div>
                                                    <span className="text-gray-500">Solicitado:</span>
                                                    <p className="font-medium text-gray-900">
                                                        {formatDate(request.createdAt)}
                                                    </p>
                                                </div>
                                                {request.approvedAt && (
                                                    <div>
                                                        <span className="text-gray-500">Aprobado:</span>
                                                        <p className="font-medium text-gray-900">
                                                            {formatDate(request.approvedAt)}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                            {request.reason && (
                                                <p className="text-sm text-gray-600 mt-2">
                                                    <span className="font-medium">Motivo:</span> {request.reason}
                                                </p>
                                            )}
                                            {request.rejectionReason && (
                                                <p className="text-sm text-red-600 mt-2">
                                                    <span className="font-medium">Motivo de rechazo:</span>{" "}
                                                    {request.rejectionReason}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {request.status === "pending" && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleCancelClick(request.id)}
                                                    className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                >
                                                    Cancelar
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Pagination */}
                    {vacationRequests.length > 0 && (
                        <div className="mt-6">
                            <PaginationControls
                                currentPage={filters.page}
                                totalPages={totalPages}
                                perPage={filters.per_page}
                                total={total}
                                onPageChange={handlePageChange}
                                onPerPageChange={handlePerPageChange}
                            />
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Cancel Confirmation Dialog */}
            <ConfirmDialog
                open={cancelDialogOpen}
                onOpenChange={setCancelDialogOpen}
                title="Cancelar Solicitud"
                description="¿Estás seguro de que deseas cancelar esta solicitud de vacaciones? Esta acción no se puede deshacer."
                confirmText="Sí, cancelar"
                cancelText="No, mantener"
                variant="destructive"
                onConfirm={handleConfirmCancel}
            />
        </div>
    );
}
export default VacationRequestsListPage;