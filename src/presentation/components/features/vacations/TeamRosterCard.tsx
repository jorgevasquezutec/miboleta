import { Briefcase, Building2, Clock, TreePalm } from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/presentation/components/ui/avatar";
import { Badge } from "@/presentation/components/ui/badge";
import { Card, CardContent } from "@/presentation/components/ui/card";
import { TeamRosterMember } from "@/core/domain/entities";
import { formatDate, formatVacationDays } from "@/presentation/utils";

interface TeamRosterCardProps {
    member: TeamRosterMember;
}

// "Sería mucho más vistoso" (ítem 43): a diferencia de las filas-hairline de
// VacationRequestCard (pensadas para escanear una bandeja de solicitudes),
// esto es un directorio de PERSONAS — una tarjeta por persona, con el estado
// codificado en forma y color (borde + insignia), no solo en una cifra.
export function TeamRosterCard({ member }: TeamRosterCardProps) {
    const initials = (member.fullName || member.email || "?")
        .split(" ")
        .filter(Boolean)
        .map((part) => part[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    const balance = member.balance;

    return (
        <Card
            className={
                member.isOnVacationNow
                    ? "border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/20"
                    : ""
            }
        >
            <CardContent className="p-4">
                <div className="flex items-start gap-3">
                    <Avatar className="h-11 w-11 shrink-0 ring-2 ring-background">
                        {member.avatarUrl && (
                            <AvatarImage src={member.avatarUrl} className="object-cover" />
                        )}
                        <AvatarFallback className="bg-blue-600 text-sm font-medium text-white">
                            {initials || "?"}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-medium text-foreground">{member.fullName}</p>
                        <p className="truncate text-sm text-muted-foreground">{member.email}</p>
                        {(member.position || member.department) && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                {member.position && (
                                    <span className="flex items-center gap-1">
                                        <Briefcase className="h-3 w-3 shrink-0" />
                                        <span className="truncate">{member.position}</span>
                                    </span>
                                )}
                                {member.department && (
                                    <span className="flex items-center gap-1">
                                        <Building2 className="h-3 w-3 shrink-0" />
                                        <span className="truncate">{member.department}</span>
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Estado: de vacaciones AHORA pesa más que una pendiente futura,
                    así que solo se pinta uno de los dos — el más urgente. */}
                {member.isOnVacationNow ? (
                    <Badge className="mt-3 gap-1 border-transparent bg-emerald-100 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/50 dark:text-emerald-300">
                        <TreePalm className="h-3 w-3" />
                        De vacaciones
                    </Badge>
                ) : (
                    member.nextPendingRequest && (
                        <Badge
                            variant="outline"
                            className="mt-3 gap-1 border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400"
                        >
                            <Clock className="h-3 w-3" />
                            Pendiente desde {formatDate(member.nextPendingRequest.startDate)}
                        </Badge>
                    )
                )}

                {balance && (
                    <div className="mt-3 flex items-baseline justify-between border-t border-border pt-3">
                        <span className="text-xs text-muted-foreground">Saldo de vacaciones</span>
                        <span className="text-base font-semibold text-foreground tabular-nums">
                            {formatVacationDays(balance.balance)}{" "}
                            <span className="text-xs font-normal text-muted-foreground">
                                {balance.balance === 1 ? "día" : "días"}
                            </span>
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default TeamRosterCard;
