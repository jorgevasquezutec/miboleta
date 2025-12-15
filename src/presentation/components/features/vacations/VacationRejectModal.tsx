import { useState } from "react";
import { XCircle, AlertTriangle, Loader2 } from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/presentation/components/ui/dialog";
import { Button } from "@/presentation/components/ui/button";
import { Textarea } from "@/presentation/components/ui/textarea";
import { Label } from "@/presentation/components/ui/label";
import { VacationRequest } from "@/core/domain/entities";

interface VacationRejectModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    request: VacationRequest | null;
    onConfirm: (id: number, reason: string) => Promise<void>;
    isLoading?: boolean;
}

export function VacationRejectModal({
    open,
    onOpenChange,
    request,
    onConfirm,
    isLoading = false,
}: VacationRejectModalProps) {
    const [reason, setReason] = useState("");
    const [error, setError] = useState("");

    const handleConfirm = async () => {
        if (!request) return;

        if (!reason.trim()) {
            setError("Debes proporcionar un motivo de rechazo");
            return;
        }

        if (reason.trim().length < 10) {
            setError("El motivo debe tener al menos 10 caracteres");
            return;
        }

        setError("");
        await onConfirm(request.id, reason.trim());
        setReason("");
    };

    const handleClose = () => {
        if (!isLoading) {
            setReason("");
            setError("");
            onOpenChange(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <XCircle className="w-5 h-5" />
                        Rechazar Solicitud de Vacaciones
                    </DialogTitle>
                    <DialogDescription>
                        Estás a punto de rechazar la solicitud de vacaciones de{" "}
                        <span className="font-medium text-gray-900">
                            {request?.user?.fullName || "este empleado"}
                        </span>
                        .
                    </DialogDescription>
                </DialogHeader>

                {request && (
                    <div className="space-y-4">
                        {/* Detalles de la solicitud */}
                        <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Fechas:</span>
                                <span className="font-medium">{request.dateRange}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Días:</span>
                                <span className="font-medium">{request.durationText}</span>
                            </div>
                            {request.reason && (
                                <div className="text-sm pt-2 border-t">
                                    <span className="text-gray-500">Motivo del empleado:</span>
                                    <p className="mt-1 text-gray-700">{request.reason}</p>
                                </div>
                            )}
                        </div>

                        {/* Alerta */}
                        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <AlertTriangle className="w-5 h-5 text-amber-600 mt-0.5" />
                            <p className="text-sm text-amber-800">
                                El empleado será notificado por correo electrónico con el motivo de rechazo.
                            </p>
                        </div>

                        {/* Campo de motivo */}
                        <div className="space-y-2">
                            <Label htmlFor="rejection-reason" className="text-sm font-medium">
                                Motivo del rechazo <span className="text-red-500">*</span>
                            </Label>
                            <Textarea
                                id="rejection-reason"
                                placeholder="Explica por qué se rechaza esta solicitud..."
                                value={reason}
                                onChange={(e) => {
                                    setReason(e.target.value);
                                    if (error) setError("");
                                }}
                                className={`min-h-[100px] ${error ? "border-red-500" : ""}`}
                                disabled={isLoading}
                            />
                            {error && <p className="text-sm text-red-500">{error}</p>}
                            <p className="text-xs text-gray-500">Mínimo 10 caracteres</p>
                        </div>
                    </div>
                )}

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={handleClose} disabled={isLoading}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={isLoading || !reason.trim()}
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                Rechazando...
                            </>
                        ) : (
                            <>
                                <XCircle className="w-4 h-4 mr-2" />
                                Confirmar Rechazo
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default VacationRejectModal;
