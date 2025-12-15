import { useState, useEffect } from "react";
import {
    Users,
    Clock,
    CheckCircle,
    Loader2,
    AlertCircle,
    Calendar,
    History,
    XCircle,
    Check,
    CalendarDays,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/presentation/components/ui/tabs";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useAuthStore } from "@/presentation/stores";
import { useUrlFilters } from "@/presentation/hooks";
import { VacationRequestCard } from "@/presentation/components/features/vacations/VacationRequestCard";
import { VacationRejectModal } from "@/presentation/components/features/vacations/VacationRejectModal";
import { VacationCalendar } from "@/presentation/components/features/vacations/VacationCalendar";
import { VacationRequest } from "@/core/domain/entities";
import { toast } from "sonner";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/presentation/components/ui/alert-dialog";

export function TeamVacationsPage() {
    const {
        pendingApprovals,
        pendingApprovalsCount,
        pendingConfirmations,
        pendingConfirmationsCount,
        myDecisions,
        myDecisionsTotal,
        myTeam,
        fetchPendingApprovals,
        fetchPendingConfirmations,
        fetchMyDecisions,
        fetchMyTeam,
        approveRequest,
        rejectRequest,
        markAsTaken,
        markAsNotTaken,
        isLoading,
        error,
    } = useVacationsStore();

    const { currentTenant } = useAuthStore();

    // URL-synced tab
    const { filters, setFilters } = useUrlFilters({
        defaultValues: {
            tab: 'pending',
        }
    });

    const [processingId, setProcessingId] = useState<number | null>(null);

    // Reject modal state
    const [rejectModalOpen, setRejectModalOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<VacationRequest | null>(null);

    // Confirm dialogs
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        type: "approve" | "taken" | "notTaken";
        requestId: number | null;
        userName: string;
    }>({
        open: false,
        type: "approve",
        requestId: null,
        userName: "",
    });

    useEffect(() => {
        loadData();
    }, [currentTenant]);

    const loadData = () => {
        fetchPendingApprovals();
        fetchPendingConfirmations();
        fetchMyDecisions();
        fetchMyTeam();
    };

    // Approve handlers
    const handleApproveClick = (request: VacationRequest) => {
        setConfirmDialog({
            open: true,
            type: "approve",
            requestId: request.id,
            userName: request.user?.fullName || "el empleado",
        });
    };

    const handleConfirmApprove = async () => {
        if (!confirmDialog.requestId) return;
        setProcessingId(confirmDialog.requestId);
        try {
            await approveRequest(confirmDialog.requestId);
            toast.success("Solicitud aprobada correctamente");
            setConfirmDialog({ ...confirmDialog, open: false });
        } catch {
            toast.error("Error al aprobar la solicitud");
        } finally {
            setProcessingId(null);
        }
    };

    // Reject handlers
    const handleRejectClick = (request: VacationRequest) => {
        setSelectedRequest(request);
        setRejectModalOpen(true);
    };

    const handleConfirmReject = async (id: number, reason: string) => {
        setProcessingId(id);
        try {
            await rejectRequest(id, reason);
            toast.success("Solicitud rechazada");
            setRejectModalOpen(false);
            setSelectedRequest(null);
        } catch {
            toast.error("Error al rechazar la solicitud");
        } finally {
            setProcessingId(null);
        }
    };

    // Mark taken handlers
    const handleMarkTakenClick = (request: VacationRequest) => {
        setConfirmDialog({
            open: true,
            type: "taken",
            requestId: request.id,
            userName: request.user?.fullName || "el empleado",
        });
    };

    const handleMarkNotTakenClick = (request: VacationRequest) => {
        setConfirmDialog({
            open: true,
            type: "notTaken",
            requestId: request.id,
            userName: request.user?.fullName || "el empleado",
        });
    };

    const handleConfirmMark = async () => {
        if (!confirmDialog.requestId) return;
        setProcessingId(confirmDialog.requestId);
        try {
            if (confirmDialog.type === "taken") {
                await markAsTaken(confirmDialog.requestId);
                toast.success("Vacaciones marcadas como tomadas");
            } else {
                await markAsNotTaken(confirmDialog.requestId);
                toast.success("Vacaciones marcadas como NO tomadas");
            }
            setConfirmDialog({ ...confirmDialog, open: false });
        } catch {
            toast.error("Error al actualizar las vacaciones");
        } finally {
            setProcessingId(null);
        }
    };

    if (error) {
        return (
            <div className="flex items-center justify-center h-96">
                <Card className="max-w-md">
                    <CardContent className="p-6 text-center">
                        <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">Error</h3>
                        <p className="text-gray-600">{error}</p>
                        <Button className="mt-4" onClick={loadData}>
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
                        <Users className="w-7 h-7 text-blue-600" />
                        Vacaciones del Equipo
                    </h1>
                    <p className="text-gray-600 mt-1">
                        Gestiona las vacaciones de tu equipo - {currentTenant?.name || ""}
                    </p>
                </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card
                    className={`cursor-pointer transition-all ${filters.tab === "pending" ? "ring-2 ring-blue-500" : "hover:shadow-md"}`}
                    onClick={() => setFilters({ tab: "pending" })}
                >
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-yellow-100">
                            <Clock className="w-6 h-6 text-yellow-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Pendientes</p>
                            <p className="text-2xl font-bold text-yellow-600">{pendingApprovalsCount}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={`cursor-pointer transition-all ${filters.tab === "confirm" ? "ring-2 ring-blue-500" : "hover:shadow-md"}`}
                    onClick={() => setFilters({ tab: "confirm" })}
                >
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-orange-100">
                            <CheckCircle className="w-6 h-6 text-orange-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Por Confirmar</p>
                            <p className="text-2xl font-bold text-orange-600">{pendingConfirmationsCount}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={`cursor-pointer transition-all ${filters.tab === "history" ? "ring-2 ring-blue-500" : "hover:shadow-md"}`}
                    onClick={() => setFilters({ tab: "history" })}
                >
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-blue-100">
                            <History className="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Mi Historial</p>
                            <p className="text-2xl font-bold text-blue-600">{myDecisionsTotal}</p>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    className={`cursor-pointer transition-all ${filters.tab === "calendar" ? "ring-2 ring-blue-500" : "hover:shadow-md"}`}
                    onClick={() => setFilters({ tab: "calendar" })}
                >
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-purple-100">
                            <CalendarDays className="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Calendario</p>
                            <p className="text-2xl font-bold text-purple-600">{myTeam.length}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Tabs Content */}
            <Tabs value={filters.tab} onValueChange={(value) => setFilters({ tab: value })} className="w-full">
                <TabsList className="grid w-full grid-cols-4">
                    <TabsTrigger value="pending" className="flex items-center gap-2">
                        <Clock className="w-4 h-4" />
                        <span className="hidden sm:inline">Pendientes</span>
                        {pendingApprovalsCount > 0 && (
                            <Badge className="ml-1 bg-yellow-500">{pendingApprovalsCount}</Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="confirm" className="flex items-center gap-2">
                        <CheckCircle className="w-4 h-4" />
                        <span className="hidden sm:inline">Confirmar</span>
                        {pendingConfirmationsCount > 0 && (
                            <Badge className="ml-1 bg-orange-500">{pendingConfirmationsCount}</Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="history" className="flex items-center gap-2">
                        <History className="w-4 h-4" />
                        <span className="hidden sm:inline">Historial</span>
                    </TabsTrigger>
                    <TabsTrigger value="calendar" className="flex items-center gap-2">
                        <CalendarDays className="w-4 h-4" />
                        <span className="hidden sm:inline">Calendario</span>
                    </TabsTrigger>
                </TabsList>

                {/* Tab: Pending Approvals */}
                <TabsContent value="pending" className="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Clock className="w-5 h-5 text-yellow-600" />
                                Solicitudes Pendientes de Aprobación
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
                                        No hay solicitudes pendientes de aprobación
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
                                            onApprove={() => handleApproveClick(request)}
                                            onReject={() => handleRejectClick(request)}
                                            isLoading={processingId === request.id}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Tab: Pending Confirmations */}
                <TabsContent value="confirm" className="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Calendar className="w-5 h-5 text-orange-600" />
                                Vacaciones Por Confirmar
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {isLoading ? (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                                </div>
                            ) : pendingConfirmations.length === 0 ? (
                                <div className="text-center py-12">
                                    <CheckCircle className="w-12 h-12 text-green-400 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        Sin confirmaciones pendientes
                                    </h3>
                                    <p className="text-gray-600">
                                        No hay vacaciones que necesiten confirmación
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {pendingConfirmations.map((request) => (
                                        <VacationRequestCard
                                            key={request.id}
                                            request={request}
                                            mode="confirmation"
                                            showActions
                                            onMarkTaken={() => handleMarkTakenClick(request)}
                                            onMarkNotTaken={() => handleMarkNotTakenClick(request)}
                                            isLoading={processingId === request.id}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Tab: My Decision History */}
                <TabsContent value="history" className="mt-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg flex items-center gap-2">
                                <History className="w-5 h-5 text-blue-600" />
                                Mi Historial de Decisiones
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {isLoading ? (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
                                </div>
                            ) : myDecisions.length === 0 ? (
                                <div className="text-center py-12">
                                    <History className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        Sin historial
                                    </h3>
                                    <p className="text-gray-600">
                                        Aún no has aprobado o rechazado ninguna solicitud
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {myDecisions.map((request) => (
                                        <VacationRequestCard
                                            key={request.id}
                                            request={request}
                                            mode="history"
                                            showActions={false}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Tab: Calendar View */}
                <TabsContent value="calendar" className="mt-6">
                    <VacationCalendar vacations={myTeam} isLoading={isLoading} />
                </TabsContent>
            </Tabs>

            {/* Reject Modal */}
            <VacationRejectModal
                open={rejectModalOpen}
                onOpenChange={setRejectModalOpen}
                request={selectedRequest}
                onConfirm={handleConfirmReject}
                isLoading={processingId === selectedRequest?.id}
            />

            {/* Approve Confirm Dialog */}
            <AlertDialog
                open={confirmDialog.open && confirmDialog.type === "approve"}
                onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, open })}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <Check className="w-5 h-5 text-green-600" />
                            ¿Aprobar solicitud?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Estás a punto de aprobar la solicitud de vacaciones de{" "}
                            <strong>{confirmDialog.userName}</strong>. Se le notificará por email.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmApprove}
                            className="bg-green-600 hover:bg-green-700 text-white"
                        >
                            Sí, aprobar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Mark Taken/NotTaken Confirm Dialog */}
            <AlertDialog
                open={confirmDialog.open && (confirmDialog.type === "taken" || confirmDialog.type === "notTaken")}
                onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, open })}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            {confirmDialog.type === "taken" ? (
                                <CheckCircle className="w-5 h-5 text-green-600" />
                            ) : (
                                <XCircle className="w-5 h-5 text-orange-600" />
                            )}
                            {confirmDialog.type === "taken" ? "¿Confirmar como tomada?" : "¿Confirmar como NO tomada?"}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmDialog.type === "taken"
                                ? `Confirmas que ${confirmDialog.userName} SÍ tomó sus vacaciones.`
                                : `Confirmas que ${confirmDialog.userName} NO tomó sus vacaciones.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmMark}
                            className={
                                confirmDialog.type === "taken"
                                    ? "bg-green-600 hover:bg-green-700 text-white"
                                    : "bg-orange-600 hover:bg-orange-700 text-white"
                            }
                        >
                            Confirmar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

export default TeamVacationsPage;
