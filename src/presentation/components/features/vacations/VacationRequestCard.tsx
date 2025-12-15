import { Calendar, User, MessageSquare, CheckCircle, XCircle } from "lucide-react";
import { Card, CardContent } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { VacationRequest } from "@/core/domain/entities";
import { VacationStatusBadge } from "./VacationStatusBadge";
import { formatDate } from "@/presentation/utils";

interface VacationRequestCardProps {
    request: VacationRequest;
    showActions?: boolean;
    onApprove?: (id: number) => void;
    onReject?: (id: number) => void;
    onMarkTaken?: (id: number) => void;
    onMarkNotTaken?: (id: number) => void;
    isLoading?: boolean;
    mode?: "approval" | "confirmation" | "view" | "history";
}

export function VacationRequestCard({
    request,
    showActions = true,
    onApprove,
    onReject,
    onMarkTaken,
    onMarkNotTaken,
    isLoading = false,
    mode = "view",
}: VacationRequestCardProps) {
    return (
        <Card className="hover:shadow-md transition-shadow">
            <CardContent className="p-4">
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    {/* Info del empleado y fechas */}
                    <div className="flex-1 space-y-3">
                        {/* Empleado */}
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold">
                                {request.user?.fullName?.charAt(0) || "?"}
                            </div>
                            <div>
                                <p className="font-medium text-gray-900">
                                    {request.user?.fullName || "Usuario desconocido"}
                                </p>
                                <p className="text-sm text-gray-500">
                                    {request.user?.documentText || request.user?.email}
                                </p>
                            </div>
                        </div>

                        {/* Estado y badges */}
                        <VacationStatusBadge
                            status={request.status}
                            wasTaken={request.wasTaken}
                            showTakenBadge={mode !== "confirmation"}
                        />

                        {/* Detalles */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            <div className="flex items-center gap-2">
                                <Calendar className="w-4 h-4 text-gray-400" />
                                <div>
                                    <span className="text-gray-500 block">Fechas</span>
                                    <span className="font-medium text-gray-900">{request.dateRange}</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <User className="w-4 h-4 text-gray-400" />
                                <div>
                                    <span className="text-gray-500 block">Días</span>
                                    <span className="font-medium text-gray-900">{request.durationText}</span>
                                </div>
                            </div>
                            <div>
                                <span className="text-gray-500 block">Solicitado</span>
                                <span className="font-medium text-gray-900">
                                    {formatDate(request.createdAt)}
                                </span>
                            </div>
                            {request.approvedAt && (
                                <div>
                                    <span className="text-gray-500 block">Aprobado</span>
                                    <span className="font-medium text-gray-900">
                                        {formatDate(request.approvedAt)}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Razón */}
                        {request.reason && (
                            <div className="flex items-start gap-2 text-sm bg-gray-50 p-2 rounded-lg">
                                <MessageSquare className="w-4 h-4 text-gray-400 mt-0.5" />
                                <div>
                                    <span className="text-gray-500">Motivo: </span>
                                    <span className="text-gray-700">{request.reason}</span>
                                </div>
                            </div>
                        )}

                        {/* Razón de rechazo */}
                        {request.rejectionReason && (
                            <div className="flex items-start gap-2 text-sm bg-red-50 p-2 rounded-lg">
                                <XCircle className="w-4 h-4 text-red-400 mt-0.5" />
                                <div>
                                    <span className="text-red-600 font-medium">Motivo de rechazo: </span>
                                    <span className="text-red-700">{request.rejectionReason}</span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Acciones */}
                    {showActions && (
                        <div className="flex flex-row lg:flex-col gap-2 lg:min-w-[140px]">
                            {mode === "approval" && request.status === "pending" && (
                                <>
                                    <Button
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => onApprove?.(request.id)}
                                        disabled={isLoading}
                                    >
                                        <CheckCircle className="w-4 h-4 mr-1" />
                                        Aprobar
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="text-red-600 hover:bg-red-50 flex-1"
                                        onClick={() => onReject?.(request.id)}
                                        disabled={isLoading}
                                    >
                                        <XCircle className="w-4 h-4 mr-1" />
                                        Rechazar
                                    </Button>
                                </>
                            )}

                            {mode === "confirmation" && request.status === "approved" && request.wasTaken === null && (
                                <>
                                    <Button
                                        size="sm"
                                        className="bg-blue-600 hover:bg-blue-700 flex-1"
                                        onClick={() => onMarkTaken?.(request.id)}
                                        disabled={isLoading}
                                    >
                                        <CheckCircle className="w-4 h-4 mr-1" />
                                        Sí, la tomó
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="text-orange-600 hover:bg-orange-50 flex-1"
                                        onClick={() => onMarkNotTaken?.(request.id)}
                                        disabled={isLoading}
                                    >
                                        <XCircle className="w-4 h-4 mr-1" />
                                        No la tomó
                                    </Button>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default VacationRequestCard;
