import { useState, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { format } from "date-fns";
import {
    ArrowLeft,
    Calendar,
    CalendarDays,
    Loader2,
    Info,
    AlertTriangle,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Label } from "@/presentation/components/ui/label";
import { Textarea } from "@/presentation/components/ui/textarea";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useAuthStore } from "@/presentation/stores";
import { toast } from "sonner";

export function VacationRequestFormPage() {
    const navigate = useNavigate();
    const { createVacationRequest, error } = useVacationsStore();
    const { user } = useAuthStore();

    const [dateRange, setDateRange] = useState<DateRange | undefined>();
    const [reason, setReason] = useState<string>("");
    const [submitting, setSubmitting] = useState(false);

    // Calculate days requested (excluding weekends)
    const daysRequested = useMemo(() => {
        if (!dateRange?.from || !dateRange?.to) return 0;

        const start = new Date(dateRange.from);
        const end = new Date(dateRange.to);

        if (end < start) return 0;

        let days = 0;
        const current = new Date(start);

        while (current <= end) {
            const dayOfWeek = current.getDay();
            // Exclude weekends (0 = Sunday, 6 = Saturday)
            if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                days++;
            }
            current.setDate(current.getDate() + 1);
        }

        return days;
    }, [dateRange]);

    // Validation
    const isValid = useMemo(() => {
        if (!dateRange?.from || !dateRange?.to) return false;
        if (daysRequested <= 0) return false;
        if (daysRequested > 30) return false;

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const start = new Date(dateRange.from);

        if (start < today) return false;

        return true;
    }, [dateRange, daysRequested]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!isValid || !dateRange?.from || !dateRange?.to) return;

        setSubmitting(true);
        try {
            await createVacationRequest({
                startDate: format(dateRange.from, 'yyyy-MM-dd'),
                endDate: format(dateRange.to, 'yyyy-MM-dd'),
                daysRequested,
                reason: reason.trim() || undefined,
            });
            toast.success("Solicitud de vacaciones creada correctamente");
            navigate("/vacations");
        } catch (err: any) {
            toast.error(err.message || "Error al crear la solicitud");
        } finally {
            setSubmitting(false);
        }
    };

    const handleBack = () => {
        navigate("/vacations");
    };

    // Format date range for display
    const formatDateRangeText = () => {
        if (!dateRange?.from || !dateRange?.to) return "";
        const options: Intl.DateTimeFormatOptions = {
            day: "2-digit",
            month: "long",
            year: "numeric",
        };
        const start = dateRange.from.toLocaleDateString("es-ES", options);
        const end = dateRange.to.toLocaleDateString("es-ES", options);
        return `${start} - ${end}`;
    };

    return (
        <div className="max-w-2xl mx-auto space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="icon" onClick={handleBack}>
                    <ArrowLeft className="w-5 h-5" />
                </Button>
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <Calendar className="w-7 h-7 text-blue-600" />
                        Nueva Solicitud de Vacaciones
                    </h1>
                    <p className="text-gray-600 mt-1">
                        Completa el formulario para solicitar tus vacaciones
                    </p>
                </div>
            </div>

            {/* No supervisor warning */}
            {user && !user.immediate_supervisor_id && (
                <Alert variant="destructive">
                    <AlertTriangle className="w-4 h-4" />
                    <AlertDescription>
                        No tienes un supervisor asignado. Contacta a RRHH para poder solicitar vacaciones.
                    </AlertDescription>
                </Alert>
            )}

            {/* Form */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                        <CalendarDays className="w-5 h-5 text-blue-600" />
                        Detalles de la Solicitud
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Date Range Selection */}
                        <div className="space-y-2">
                            <Label>Rango de Fechas *</Label>
                            <DateRangePicker
                                initialDateFrom={dateRange?.from}
                                initialDateTo={dateRange?.to}
                                onUpdate={({ range }) => setDateRange(range)}
                                showCompare={false}
                                align="start"
                            />
                            <p className="text-xs text-gray-500">
                                Selecciona las fechas de inicio y fin de tus vacaciones
                            </p>
                        </div>

                        {/* Days Summary */}
                        {daysRequested > 0 && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-blue-100 rounded-full">
                                        <Calendar className="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-blue-700">Días solicitados (hábiles)</p>
                                        <p className="text-2xl font-bold text-blue-900">
                                            {daysRequested} {daysRequested === 1 ? "día" : "días"}
                                        </p>
                                        {formatDateRangeText() && (
                                            <p className="text-sm text-blue-600 mt-1">{formatDateRangeText()}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Validation warnings */}
                        {daysRequested > 30 && (
                            <Alert variant="destructive">
                                <AlertTriangle className="w-4 h-4" />
                                <AlertDescription>
                                    No puedes solicitar más de 30 días en una sola solicitud.
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Reason */}
                        <div className="space-y-2">
                            <Label htmlFor="reason">Motivo (Opcional)</Label>
                            <Textarea
                                id="reason"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                placeholder="Describe el motivo de tu solicitud (opcional)"
                                rows={3}
                                maxLength={1000}
                                className="resize-none"
                            />
                            <p className="text-xs text-gray-500 text-right">
                                {reason.length}/1000 caracteres
                            </p>
                        </div>

                        {/* Info box */}
                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div className="flex items-start gap-3">
                                <Info className="w-5 h-5 text-gray-500 mt-0.5" />
                                <div className="text-sm text-gray-600">
                                    <p className="font-medium text-gray-900 mb-1">
                                        ¿Cómo funciona el proceso?
                                    </p>
                                    <ol className="list-decimal list-inside space-y-1">
                                        <li>Envías tu solicitud</li>
                                        <li>Tu supervisor recibe una notificación</li>
                                        <li>El supervisor aprueba o rechaza</li>
                                        <li>Recibes un email con la respuesta</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        {/* Error display */}
                        {error && (
                            <Alert variant="destructive">
                                <AlertTriangle className="w-4 h-4" />
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}

                        {/* Actions */}
                        <div className="flex items-center justify-end gap-3 pt-4 border-t">
                            <Button type="button" variant="outline" onClick={handleBack}>
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={!isValid || submitting || !user?.immediate_supervisor_id}
                            >
                                {submitting ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Enviando...
                                    </>
                                ) : (
                                    <>
                                        <Calendar className="w-4 h-4 mr-2" />
                                        Enviar Solicitud
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

export default VacationRequestFormPage;
