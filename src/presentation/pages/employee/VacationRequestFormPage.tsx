import { useState, useMemo } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useNavigate } from "react-router-dom";
import { format } from "date-fns";
import {
    ArrowLeft,
    Calendar,
    CalendarDays,
    Loader2,
    Info,
    AlertTriangle,
    Building2,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Button } from "@/presentation/components/ui/button";
import { Label } from "@/presentation/components/ui/label";
import { Textarea } from "@/presentation/components/ui/textarea";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { TenantSelector } from "@/presentation/components/shared/TenantSelector";
import { useVacationsStore } from "@/presentation/stores/vacationsStore";
import { useAuthStore } from "@/presentation/stores";
import { toast } from "sonner";

export function VacationRequestFormPage() {
    useDocumentTitle('Nueva Solicitud de Vacaciones');
    const navigate = useNavigate();
    const { createVacationRequest, error, clearError } = useVacationsStore();
    const { user } = useAuthStore();

    const [selectedTenantId, setSelectedTenantId] = useState<string>("");
    const [dateRange, setDateRange] = useState<DateRange | undefined>();
    const [reason, setReason] = useState<string>("");
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    // Check if user has multiple tenants
    const hasMultipleTenants = user?.tenants && user.tenants.length > 1;

    console.log('👤 [Vacation Form] User info:', {
        userId: user?.id,
        userName: user?.name,
        tenantsCount: user?.tenants?.length,
        tenants: user?.tenants,
        hasMultipleTenants,
    });

    // Get supervisor for selected tenant (or default tenant)
    const selectedTenant = useMemo(() => {
        if (!user?.tenants) return null;
        if (hasMultipleTenants && selectedTenantId) {
            return user.tenants.find(t => String(t.id) === selectedTenantId);
        }
        // If single tenant, use it directly
        return user.tenants[0];
    }, [user?.tenants, hasMultipleTenants, selectedTenantId]);

    const hasSupervisor = selectedTenant?.supervisor_id != null;

    console.log('🏢 [Vacation Form] Selected tenant:', {
        selectedTenantId,
        selectedTenant: selectedTenant ? { id: selectedTenant.id, name: selectedTenant.name, supervisor_id: selectedTenant.supervisor_id } : null,
        hasSupervisor,
    });

    // Calculate days requested (all calendar days)
    const daysRequested = useMemo(() => {
        if (!dateRange?.from || !dateRange?.to) return 0;

        const start = new Date(dateRange.from);
        const end = new Date(dateRange.to);

        if (end < start) return 0;

        // Calculate difference in days (inclusive)
        const diffTime = end.getTime() - start.getTime();
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;

        return diffDays;
    }, [dateRange]);

    // Validation errors (frontend)
    const frontendErrors = useMemo(() => {
        const errors: Record<string, string> = {};

        if (dateRange?.from || dateRange?.to) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const start = new Date(dateRange.from!);
            start.setHours(0, 0, 0, 0);

            // Validar fecha de inicio en el pasado
            if (start < today) {
                errors.start_date = "La fecha de inicio debe ser hoy o una fecha futura.";
            }

            // Validar días solicitados
            if (daysRequested <= 0) {
                errors.days_requested = "Debes seleccionar al menos un día.";
            } else if (daysRequested > 30) {
                errors.days_requested = "No puedes solicitar más de 30 días en una sola solicitud.";
            }
        }

        // Validar razón (opcional pero si se ingresa debe ser válida)
        if (reason.length > 1000) {
            errors.reason = "El motivo no puede exceder 1000 caracteres.";
        }

        return errors;
    }, [dateRange, daysRequested, reason]);

    // Validation
    const isValid = useMemo(() => {
        const validationLog = {
            hasDateRange: !!(dateRange?.from && dateRange?.to),
            hasMultipleTenants,
            selectedTenantId,
            selectedTenant: selectedTenant ? { id: selectedTenant.id, name: selectedTenant.name, supervisor_id: selectedTenant.supervisor_id } : null,
            hasSupervisor,
            frontendErrorsCount: Object.keys(frontendErrors).length,
            frontendErrors,
        };

        console.log('🔍 [Vacation Form] Validation check:', validationLog);

        if (!dateRange?.from || !dateRange?.to) {
            console.log('❌ [Validation] Missing date range');
            return false;
        }
        if (hasMultipleTenants && !selectedTenantId) {
            console.log('❌ [Validation] Multi-tenant user needs to select tenant');
            return false;
        }
        if (!hasSupervisor) {
            console.log('❌ [Validation] No supervisor assigned to selected tenant');
            return false;
        }
        if (Object.keys(frontendErrors).length > 0) {
            console.log('❌ [Validation] Frontend errors:', frontendErrors);
            return false;
        }

        console.log('✅ [Validation] All checks passed');
        return true;
    }, [dateRange, hasMultipleTenants, selectedTenantId, hasSupervisor, frontendErrors, selectedTenant]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!isValid || !dateRange?.from || !dateRange?.to) {
            // Si hay errores de validación frontend, mostrar un toast
            if (Object.keys(frontendErrors).length > 0) {
                const firstError = Object.values(frontendErrors)[0];
                toast.error(firstError);
            }
            return;
        }

        setSubmitting(true);
        setFieldErrors({});
        clearError();

        try {
            await createVacationRequest({
                startDate: format(dateRange.from, 'yyyy-MM-dd'),
                endDate: format(dateRange.to, 'yyyy-MM-dd'),
                daysRequested,
                reason: reason.trim() || undefined,
                tenantId: hasMultipleTenants ? parseInt(selectedTenantId) : undefined,
            });
            toast.success("Solicitud de vacaciones creada correctamente");
            navigate("/vacations");
        } catch (err: any) {
            // Extraer errores de validación por campo si existen
            if (err.response?.data?.errors) {
                const errors: Record<string, string> = {};
                Object.keys(err.response.data.errors).forEach((key) => {
                    const errorMessages = err.response.data.errors[key];
                    if (Array.isArray(errorMessages) && errorMessages.length > 0) {
                        errors[key] = errorMessages[0];
                    }
                });
                setFieldErrors(errors);
            }

            // Mostrar toast con el error general
            const errorMessage = err.response?.data?.message || error || "Error al crear la solicitud";
            toast.error(errorMessage);
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

            {/* Alerta cuando no hay supervisor asignado */}
            {selectedTenant && !hasSupervisor && (
                <Alert variant="destructive">
                    <AlertTriangle className="w-4 h-4" />
                    <AlertDescription>
                        No tienes un supervisor asignado para <strong>{selectedTenant.name}</strong>.
                        Contacta a RRHH para poder solicitar vacaciones.
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
                        {/* Tenant Selection (only if user has multiple tenants) */}
                        {hasMultipleTenants && (
                            <div className="space-y-2">
                                <Label htmlFor="tenant">Empresa *</Label>
                                <TenantSelector
                                    value={selectedTenantId}
                                    onValueChange={setSelectedTenantId}
                                    placeholder="Selecciona la empresa para la solicitud"
                                />
                                {!selectedTenantId && (
                                    <p className="text-xs text-gray-500 flex items-center gap-1">
                                        <Building2 className="w-3 h-3" />
                                        Selecciona la empresa a la que corresponde esta solicitud
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Submit={handleSubmit} className="space-y-6">
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
                            {(frontendErrors.start_date || fieldErrors.start_date) && (
                                <p className="text-xs text-red-600 flex items-center gap-1">
                                    <AlertTriangle className="w-3 h-3" />
                                    {frontendErrors.start_date || fieldErrors.start_date}
                                </p>
                            )}
                            {(frontendErrors.end_date || fieldErrors.end_date) && (
                                <p className="text-xs text-red-600 flex items-center gap-1">
                                    <AlertTriangle className="w-3 h-3" />
                                    {frontendErrors.end_date || fieldErrors.end_date}
                                </p>
                            )}
                            {!frontendErrors.start_date && !fieldErrors.start_date && !frontendErrors.end_date && !fieldErrors.end_date && (
                                <p className="text-xs text-gray-500">
                                    Selecciona las fechas de inicio y fin de tus vacaciones
                                </p>
                            )}
                        </div>

                        {/* Days Summary */}
                        {daysRequested > 0 && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-blue-100 rounded-full">
                                        <Calendar className="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-blue-700">Días solicitados</p>
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

                        {/* Days requested validation error */}
                        {(frontendErrors.days_requested || fieldErrors.days_requested) && (
                            <Alert variant="destructive">
                                <AlertTriangle className="w-4 h-4" />
                                <AlertDescription>
                                    {frontendErrors.days_requested || fieldErrors.days_requested}
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
                            {(frontendErrors.reason || fieldErrors.reason) ? (
                                <p className="text-xs text-red-600 flex items-center gap-1">
                                    <AlertTriangle className="w-3 h-3" />
                                    {frontendErrors.reason || fieldErrors.reason}
                                </p>
                            ) : (
                                <p className="text-xs text-gray-500 text-right">
                                    {reason.length}/1000 caracteres
                                </p>
                            )}
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
                                disabled={!isValid || submitting || !hasSupervisor}
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
