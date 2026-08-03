import { Info } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/presentation/components/ui/popover";
import { VacationRequest, VacationStatus } from "@/core/domain/entities";
import { formatDate, formatVacationDays } from "@/presentation/utils";

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

// Orden de la fórmula (Saldo = Pendientes + Truncas − Gozadas), no el orden en
// que se calculan: así los tres desgloses se leen como los sumandos del Saldo.
const BALANCE_CONCEPTS = [
    {
        label: "Pend.",
        field: "pending",
        help: "Días de los años de servicio ya cumplidos. Incluye los que ya se tomaron: por eso puede ser mayor que el Saldo.",
        fullLabel: "Pendientes",
    },
    {
        label: "Trunc.",
        field: "truncated",
        help: "Devengo proporcional del año laboral en curso, por dozavos y treintavos (D.S. 012-92-TR).",
        fullLabel: "Truncas",
    },
    {
        label: "Goz.",
        field: "taken",
        help: "Días ya aprobados o confirmados como tomados.",
        fullLabel: "Gozadas",
    },
] as const;

// Estado como punto de color + texto, no como píldora rellena: en una bandeja
// de N filas, N píldoras amarillas son N focos de atención compitiendo con la
// única acción que importa. El punto da el mismo dato con una fracción del peso
// visual. (VacationStatusBadge se mantiene intacto para el resto de pantallas.)
const STATUS_DOT: Record<VacationStatus, string> = {
    pending: "bg-amber-500",
    approved: "bg-emerald-500",
    rejected: "bg-red-500",
    cancelled: "bg-muted-foreground",
};

const dayLabel = (value: number) => (value === 1 ? "día" : "días");

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
    // El saldo solo lo embebe el backend en los listados del aprobador. En
    // "view" (Mis Vacaciones del empleado) no se pinta aunque llegara: esa
    // pantalla ya tiene su propio panel de saldo y aquí sería duplicado.
    const balance = mode === "view" ? null : request.vacationBalance;

    const daysRequested = Number(request.daysRequested);

    // Veredicto: la resta que el aprobador tendría que hacer de cabeza. Solo
    // mientras la decisión sigue abierta — en Historial o Por Confirmar ya se
    // aprobó y hablar de "tras aprobar" sería incoherente.
    const showVerdict = !!balance && mode === "approval" && request.status === "pending";
    const remainingAfterApproval = balance ? balance.balance - daysRequested : 0;
    // No bloquea "Aprobar": la decisión es del aprobador, y el backend tampoco
    // la impide (approveRequest no revalida el saldo).
    const exceedsBalance = showVerdict && remainingAfterApproval < 0;
    const excessDays = Math.abs(remainingAfterApproval);

    const showTakenState =
        mode !== "confirmation" && request.status === "approved" && request.wasTaken != null;

    return (
        /* Fila de bandeja, no tarjeta suelta: el contenedor de la página aporta
           el borde y las líneas divisorias (divide-y), así N solicitudes son N
           hairlines en vez de N tarjetas con borde, sombra y separación. Menos
           marcos = menos carga visual, y más densidad de scaneo.
           Toda la fila es neutra a propósito: el único color saturado es el
           botón Aprobar, que es la acción que importa. El ámbar aparece solo
           cuando hay un riesgo real que señalar. */
        <div className="flex flex-col gap-3 px-6 py-3.5 transition-colors hover:bg-muted/40 lg:flex-row lg:items-center">
            {/* Identidad y solicitud */}
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground">
                        {request.user?.fullName?.charAt(0).toUpperCase() || "?"}
                    </span>
                    <span className="truncate font-medium text-foreground">
                        {request.user?.fullName || "Usuario desconocido"}
                    </span>
                    <span className="shrink-0 text-sm text-muted-foreground tabular-nums">
                        {request.user?.documentText || request.user?.email}
                    </span>
                    <span className="flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${STATUS_DOT[request.status]}`}
                            aria-hidden="true"
                        />
                        {request.statusLabel}
                        {showTakenState && (
                            <span>· {request.wasTaken ? "Tomada" : "No tomada"}</span>
                        )}
                    </span>
                </div>

                {/* Una sola línea de metadatos, con truncate: así todas las filas
                    de la bandeja miden exactamente lo mismo y el ojo puede
                    recorrer la columna sin saltos. Sin iconos: las fechas y los
                    días se explican solos, y cada glifo extra es ruido. */}
                <p className="mt-0.5 truncate pl-9 text-sm text-muted-foreground">
                    {/* Los días pedidos van PRIMEROS y con peso: son el dato
                        central de la solicitud, y enfrentan visualmente al
                        saldo del otro extremo de la fila (18 días pedidos vs
                        47.5 de saldo). El rango de fechas es el detalle. */}
                    <span className="font-semibold text-foreground tabular-nums">
                        {request.durationText}
                    </span>
                    {" · "}
                    <span className="tabular-nums">{request.dateRange}</span>
                    {" · "}
                    Solicitado {formatDate(request.createdAt)}
                    {request.approvedAt && <> · Aprobado {formatDate(request.approvedAt)}</>}
                    {request.reason && (
                        <>
                            {" · "}
                            <span className="italic" title={request.reason}>
                                “{request.reason}”
                            </span>
                        </>
                    )}
                </p>

                {request.rejectionReason && (
                    <p className="mt-0.5 truncate pl-9 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">Motivo del rechazo: </span>
                        {request.rejectionReason}
                    </p>
                )}
            </div>

            {/* Saldo del solicitante (SPEC-VACACIONES v2) para la empresa de ESTA
                solicitud, no la activa del switcher. Sin caja tintada: alineado
                a la derecha y con tabular-nums, las cifras forman columna entre
                filas y se comparan de un vistazo, que es justo lo que una caja
                de color impide. */}
            {balance && (
                <div className="shrink-0 pl-9 lg:w-56 lg:pl-0 lg:text-right">
                    <div className="flex items-baseline gap-1.5 lg:justify-end">
                        <span className="text-base font-semibold text-foreground tabular-nums">
                            {formatVacationDays(balance.balance)}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {balance.balance === 1 ? "día de saldo" : "días de saldo"}
                        </span>
                        {/* Popover y no `title`: el tooltip nativo no existe en
                            táctil ni con teclado, y estos conceptos son los que
                            más explicación piden. p-1 -m-1 agranda el área
                            táctil a 22px sin mover el layout. */}
                        <Popover>
                            <PopoverTrigger
                                className="-m-1 rounded-full p-1 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                aria-label="Qué significa cada cifra del saldo"
                            >
                                <Info className="h-3.5 w-3.5" />
                            </PopoverTrigger>
                            <PopoverContent align="end" className="space-y-2 text-sm">
                                <p className="font-medium">Cómo se calcula el saldo</p>
                                <p className="text-muted-foreground">
                                    Saldo = Pendientes + Truncas − Gozadas
                                </p>
                                <dl className="space-y-1.5">
                                    {BALANCE_CONCEPTS.map((concept) => (
                                        <div key={concept.field}>
                                            <dt className="font-medium">{concept.fullLabel}</dt>
                                            <dd className="text-muted-foreground">{concept.help}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <p className="text-xs text-muted-foreground tabular-nums">
                        {BALANCE_CONCEPTS.map((concept, index) => (
                            <span key={concept.field}>
                                {index > 0 && " · "}
                                {concept.label} {formatVacationDays(balance[concept.field])}
                            </span>
                        ))}
                    </p>

                    {showVerdict &&
                        (exceedsBalance ? (
                            <p className="text-xs font-medium text-amber-600 tabular-nums dark:text-amber-500">
                                Excede por {formatVacationDays(excessDays)} {dayLabel(excessDays)}
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground tabular-nums">
                                Le quedarían{" "}
                                <span className="font-medium text-foreground">
                                    {formatVacationDays(remainingAfterApproval)}{" "}
                                    {dayLabel(remainingAfterApproval)}
                                </span>
                            </p>
                        ))}
                </div>
            )}

            {/* Acciones. "Aprobar" es el único elemento saturado de la fila; el
                secundario va en ghost y solo se tiñe de rojo al pasar por
                encima — una acción destructiva no debería gritar tan fuerte como
                la principal mientras nadie la está mirando. */}
            {showActions && (
                <div className="flex shrink-0 items-center gap-1 pl-9 lg:justify-end lg:pl-0">
                    {mode === "approval" && request.status === "pending" && (
                        <>
                            <Button
                                size="sm"
                                onClick={() => onApprove?.(request.id)}
                                disabled={isLoading}
                            >
                                Aprobar
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                onClick={() => onReject?.(request.id)}
                                disabled={isLoading}
                            >
                                Rechazar
                            </Button>
                        </>
                    )}

                    {mode === "confirmation" && request.status === "approved" && request.wasTaken === null && (
                        <>
                            <Button
                                size="sm"
                                onClick={() => onMarkTaken?.(request.id)}
                                disabled={isLoading}
                            >
                                Sí, la tomó
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-muted-foreground hover:bg-orange-50 hover:text-orange-600 dark:hover:bg-orange-950/40 dark:hover:text-orange-400"
                                onClick={() => onMarkNotTaken?.(request.id)}
                                disabled={isLoading}
                            >
                                No la tomó
                            </Button>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

export default VacationRequestCard;
