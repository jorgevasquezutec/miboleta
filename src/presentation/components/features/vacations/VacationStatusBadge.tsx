import { Clock, CheckCircle, XCircle, Ban, Check, X } from "lucide-react";
import { Badge } from "@/presentation/components/ui/badge";
import { VacationStatus } from "@/core/domain/entities";

interface VacationStatusBadgeProps {
    status: VacationStatus;
    wasTaken?: boolean | null;
    showTakenBadge?: boolean;
    size?: "sm" | "md";
}

const statusConfig: Record<
    VacationStatus,
    {
        label: string;
        className: string;
        icon: React.ReactNode;
    }
> = {
    pending: {
        label: "Pendiente",
        className: "bg-yellow-100 text-yellow-800 border-yellow-200",
        icon: <Clock className="w-3 h-3" />,
    },
    approved: {
        label: "Aprobada",
        className: "bg-green-100 text-green-800 border-green-200",
        icon: <CheckCircle className="w-3 h-3" />,
    },
    rejected: {
        label: "Rechazada",
        className: "bg-red-100 text-red-800 border-red-200",
        icon: <XCircle className="w-3 h-3" />,
    },
    cancelled: {
        label: "Cancelada",
        className: "bg-gray-100 text-gray-800 border-gray-200",
        icon: <Ban className="w-3 h-3" />,
    },
};

export function VacationStatusBadge({
    status,
    wasTaken,
    showTakenBadge = true,
    size = "md",
}: VacationStatusBadgeProps) {
    const config = statusConfig[status];
    const sizeClass = size === "sm" ? "text-xs py-0.5 px-1.5" : "text-sm py-1 px-2";

    return (
        <div className="flex items-center gap-2 flex-wrap">
            <Badge
                variant="outline"
                className={`${config.className} ${sizeClass} flex items-center gap-1`}
            >
                {config.icon}
                {config.label}
            </Badge>

            {/* Badge de "Tomada/No Tomada" solo para aprobadas y si aplica */}
            {showTakenBadge && status === "approved" && wasTaken !== undefined && wasTaken !== null && (
                <Badge
                    variant="outline"
                    className={`${sizeClass} flex items-center gap-1 ${wasTaken
                            ? "bg-blue-100 text-blue-800 border-blue-200"
                            : "bg-orange-100 text-orange-800 border-orange-200"
                        }`}
                >
                    {wasTaken ? (
                        <>
                            <Check className="w-3 h-3" />
                            Tomada
                        </>
                    ) : (
                        <>
                            <X className="w-3 h-3" />
                            No tomada
                        </>
                    )}
                </Badge>
            )}
        </div>
    );
}

export default VacationStatusBadge;
