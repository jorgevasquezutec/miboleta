import { useState, useEffect } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import {
    ClipboardList,
    Loader2,
    AlertCircle,
    Calendar,
    CheckCircle,
    Clock,
    RefreshCw,
    Check,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { VacationRequestCard } from "@/presentation/components/features/vacations";
import { toast } from "sonner";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";

export function VacationConfirmationPage() {
    useDocumentTitle('Confirmar Vacaciones');
    const {
        pendingConfirmations,
        pendingConfirmationsCount,
        fetchPendingConfirmations,
        markAsTaken,
        markAsNotTaken,
        isLoading,
        error,
    } = useVacationsStore();

    const [processingId, setProcessingId] = useState<number | null>(null);
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        type: "taken" | "notTaken";
        requestId: number | null;
        userName: string;
    }>({
        open: false,
        type: "taken",
        requestId: null,
        userName: "",
    });

    useEffect(() => {
        fetchPendingConfirmations();
    }, [fetchPendingConfirmations]);

    const handleMarkTakenClick = (id: number) => {
        const request = pendingConfirmations.find((r) => r.id === id);
        setConfirmDialog({
            open: true,
            type: "taken",
            requestId: id,
            userName: request?.user?.fullName || "este empleado",
        });
    };

    const handleMarkNotTakenClick = (id: number) => {
        const request = pendingConfirmations.find((r) => r.id === id);
        setConfirmDialog({
            open: true,
            type: "notTaken",
            requestId: id,
            userName: request?.user?.fullName || "este empleado",
        });
    };

    const handleConfirmAction = async () => {
        if (!confirmDialog.requestId) return;

        setProcessingId(confirmDialog.requestId);
        try {
            if (confirmDialog.type === "taken") {
                await markAsTaken(confirmDialog.requestId);
                toast.success("Vacaciones marcadas como tomadas");
            } else {
                await markAsNotTaken(confirmDialog.requestId);
                toast.success("Vacaciones marcadas como no tomadas");
            }
        } catch {
            toast.error("No se pudo actualizar el estado de las vacaciones");
        } finally {
            setProcessingId(null);
            setConfirmDialog((prev) => ({ ...prev, open: false }));
        }
    };

    const handleRefresh = () => {
        fetchPendingConfirmations();
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
        // max-w-7xl: ver nota en TeamVacationsPage — sin tope, las filas de
        // solicitud se estiran y abren un hueco entre datos y acciones.
        <div className="space-y-6 max-w-7xl">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <ClipboardList className="w-6 h-6 sm:w-7 sm:h-7 text-blue-600" />
                        Confirmar Vacaciones
                    </h1>
                    <p className="text-gray-600 mt-1 text-sm sm:text-base">
                        Confirma si las vacaciones aprobadas fueron efectivamente tomadas
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
                        <div className="p-3 rounded-full bg-orange-100">
                            <Clock className="w-6 h-6 text-orange-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Pendientes de Confirmar</p>
                            <p className="text-2xl font-bold text-orange-600">
                                {pendingConfirmationsCount}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4 flex items-center gap-4">
                        <div className="p-3 rounded-full bg-green-100">
                            <Check className="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <p className="text-sm text-gray-600">Tomadas Este Mes</p>
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
                            <p className="text-sm text-gray-600">Próximas Vacaciones</p>
                            <p className="text-2xl font-bold text-blue-600">-</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Info Box */}
            <Card className="border-blue-200 bg-blue-50">
                <CardContent className="p-4 flex items-start gap-3">
                    <AlertCircle className="w-5 h-5 text-blue-600 mt-0.5" />
                    <div className="text-sm text-blue-800">
                        <p className="font-medium">¿Para qué sirve esta confirmación?</p>
                        <p className="mt-1">
                            Después de aprobar vacaciones, es importante confirmar si el empleado
                            efectivamente las tomó. Esto permite llevar un registro preciso de los
                            días de vacaciones utilizados y calcular correctamente el saldo disponible.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Vacaciones Pendientes de Confirmar */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="text-lg flex items-center gap-2">
                        Vacaciones Pendientes de Confirmar
                        {pendingConfirmationsCount > 0 && (
                            <Badge className="bg-orange-100 text-orange-800 border-orange-200">
                                {pendingConfirmationsCount}
                            </Badge>
                        )}
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
                                ¡Todo confirmado!
                            </h3>
                            <p className="text-gray-600">
                                No tienes vacaciones pendientes por confirmar
                            </p>
                        </div>
                    ) : (
                        // Ver nota en TeamVacationsPage: lista con hairlines a
                        // sangre del Card en vez de tarjetas sueltas.
                        <div className="-mx-6 divide-y divide-border border-t border-border">
                            {pendingConfirmations.map((request) => (
                                <VacationRequestCard
                                    key={request.id}
                                    request={request}
                                    mode="confirmation"
                                    showActions
                                    onMarkTaken={handleMarkTakenClick}
                                    onMarkNotTaken={handleMarkNotTakenClick}
                                    isLoading={processingId === request.id}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Confirm Dialog */}
            <ConfirmDialog
                open={confirmDialog.open}
                onOpenChange={(open) => setConfirmDialog((prev) => ({ ...prev, open }))}
                title={
                    confirmDialog.type === "taken"
                        ? "Confirmar Vacaciones Tomadas"
                        : "Confirmar Vacaciones No Tomadas"
                }
                description={
                    confirmDialog.type === "taken"
                        ? `¿Confirmas que ${confirmDialog.userName} efectivamente tomó estas vacaciones?`
                        : `¿Confirmas que ${confirmDialog.userName} NO tomó estas vacaciones? Los días no se descontarán de su saldo.`
                }
                confirmText={confirmDialog.type === "taken" ? "Sí, las tomó" : "No las tomó"}
                cancelText="Cancelar"
                variant={confirmDialog.type === "taken" ? "default" : "destructive"}
                onConfirm={handleConfirmAction}
            />
        </div>
    );
}

export default VacationConfirmationPage;
