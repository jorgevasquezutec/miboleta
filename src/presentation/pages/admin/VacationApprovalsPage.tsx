import { useState, useEffect } from "react";
import {
    ClipboardCheck,
    Loader2,
    AlertCircle,
    Calendar,
    CheckCircle,
    Clock,
    RefreshCw,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { VacationRequestCard, VacationRejectModal } from "@/presentation/components/features/vacations";
import { VacationRequest } from "@/core/domain/entities";
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

    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<VacationRequest | null>(null);
    const [processingId, setProcessingId] = useState<number | null>(null);

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
                <Button variant="outline" onClick={handleRefresh} disabled={isLoading}>
                    <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? "animate-spin" : ""}`} />
                    Actualizar
                </Button>
            </div>

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
                        {pendingApprovalsCount > 0 && (
                            <Badge className="bg-yellow-100 text-yellow-800 border-yellow-200">
                                {pendingApprovalsCount}
                            </Badge>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                        </div>
                    ) : pendingApprovals.length === 0 ? (
                        <div className="text-center py-12">
                            <CheckCircle className="w-12 h-12 text-green-400 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                ¡Todo al día!
                            </h3>
                            <p className="text-gray-600">
                                No tienes solicitudes pendientes de aprobar
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {pendingApprovals.map((request) => (
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
