import { useState, useMemo } from 'react';
import { parseISO } from 'date-fns';
import { ChevronLeft, ChevronRight, Users } from 'lucide-react';
import { Button } from '@/presentation/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/presentation/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/presentation/components/ui/tooltip';
import { VacationRequest } from '@/core/domain/entities';
import { cn } from '@/presentation/components/ui/utils';

interface VacationCalendarProps {
    vacations: VacationRequest[];
    isLoading?: boolean;
}

// Status colors
const STATUS_COLORS = {
    approved: { bg: 'bg-green-500', text: 'Aprobadas', border: 'border-green-500' },
    pending: { bg: 'bg-yellow-500', text: 'Pendientes', border: 'border-yellow-500' },
    rejected: { bg: 'bg-red-500', text: 'Rechazadas', border: 'border-red-500' },
    taken: { bg: 'bg-blue-500', text: 'Tomadas', border: 'border-blue-500' },
};

const DAYS_OF_WEEK = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const MONTHS = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

export function VacationCalendar({ vacations, isLoading }: VacationCalendarProps) {
    const [currentDate, setCurrentDate] = useState(new Date());

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    // Navigate months
    const goToPreviousMonth = () => {
        setCurrentDate(new Date(year, month - 1, 1));
    };

    const goToNextMonth = () => {
        setCurrentDate(new Date(year, month + 1, 1));
    };

    const goToToday = () => {
        setCurrentDate(new Date());
    };

    // Get calendar days
    const calendarDays = useMemo(() => {
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();

        // Monday = 0, Sunday = 6 (ajustado desde getDay donde Sunday = 0)
        let startDayOfWeek = firstDay.getDay() - 1;
        if (startDayOfWeek < 0) startDayOfWeek = 6;

        const days: { date: Date | null; day: number | null; isToday: boolean }[] = [];

        // Add empty cells for days before the first of the month
        for (let i = 0; i < startDayOfWeek; i++) {
            days.push({ date: null, day: null, isToday: false });
        }

        // Add days of the month
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const isToday =
                date.getDate() === today.getDate() &&
                date.getMonth() === today.getMonth() &&
                date.getFullYear() === today.getFullYear();
            days.push({ date, day, isToday });
        }

        return days;
    }, [year, month]);

    // Get vacations for a specific date
    const getVacationsForDate = (date: Date) => {
        return vacations.filter(vacation => {
            // vacation.startDate/endDate son `YYYY-MM-DD` (solo fecha).
            // `parseISO` los interpreta en la zona LOCAL en vez de UTC, a
            // diferencia de `new Date(...)`, que en zonas detrás de UTC
            // (Perú, UTC-5) los retrocedía un día y desalineaba el calendario.
            const startDate = parseISO(vacation.startDate);
            const endDate = parseISO(vacation.endDate);

            // Normalize dates to compare only date part
            startDate.setHours(0, 0, 0, 0);
            endDate.setHours(23, 59, 59, 999);
            const checkDate = new Date(date);
            checkDate.setHours(12, 0, 0, 0);

            return checkDate >= startDate && checkDate <= endDate;
        });
    };

    // Get status for rendering
    const getVacationStatus = (vacation: VacationRequest) => {
        if (vacation.wasTaken === true) return 'taken';
        if (vacation.status === 'approved') return 'approved';
        if (vacation.status === 'pending') return 'pending';
        if (vacation.status === 'rejected') return 'rejected';
        return 'pending';
    };

    if (isLoading) {
        return (
            <Card>
                <CardContent className="p-8 text-center">
                    <div className="animate-pulse space-y-4">
                        <div className="h-8 bg-gray-200 rounded w-1/3 mx-auto" />
                        <div className="grid grid-cols-7 gap-2">
                            {Array.from({ length: 35 }).map((_, i) => (
                                <div key={i} className="h-20 bg-gray-100 rounded" />
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="w-5 h-5" />
                        Calendario de Vacaciones
                    </CardTitle>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" onClick={goToToday}>
                            Hoy
                        </Button>
                        <Button variant="outline" size="icon" onClick={goToPreviousMonth}>
                            <ChevronLeft className="w-4 h-4" />
                        </Button>
                        <span className="font-semibold min-w-[150px] text-center">
                            {MONTHS[month]} {year}
                        </span>
                        <Button variant="outline" size="icon" onClick={goToNextMonth}>
                            <ChevronRight className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {/* Legend */}
                <div className="flex flex-wrap gap-4 mb-4 pb-4 border-b">
                    {Object.entries(STATUS_COLORS).map(([key, value]) => (
                        <div key={key} className="flex items-center gap-2">
                            <div className={cn('w-3 h-3 rounded-full', value.bg)} />
                            <span className="text-sm text-gray-600">{value.text}</span>
                        </div>
                    ))}
                </div>

                {/* Calendar Grid */}
                <div className="grid grid-cols-7 gap-1">
                    {/* Day headers */}
                    {DAYS_OF_WEEK.map(day => (
                        <div
                            key={day}
                            className="text-center text-sm font-medium text-gray-500 py-2"
                        >
                            {day}
                        </div>
                    ))}

                    {/* Calendar days */}
                    {calendarDays.map((dayInfo, index) => {
                        if (!dayInfo.date) {
                            return <div key={index} className="min-h-[80px] bg-gray-50 rounded" />;
                        }

                        const dayVacations = getVacationsForDate(dayInfo.date);
                        const hasVacations = dayVacations.length > 0;

                        return (
                            <div
                                key={index}
                                className={cn(
                                    'min-h-[80px] p-1 border rounded relative',
                                    dayInfo.isToday ? 'border-blue-500 border-2' : 'border-gray-200',
                                    hasVacations ? 'bg-gray-50' : 'bg-white'
                                )}
                            >
                                <div
                                    className={cn(
                                        'text-sm font-medium mb-1',
                                        dayInfo.isToday ? 'text-blue-600' : 'text-gray-700'
                                    )}
                                >
                                    {dayInfo.day}
                                </div>

                                {/* Vacation indicators */}
                                <div className="space-y-0.5">
                                    {dayVacations.slice(0, 3).map((vacation) => {
                                        const status = getVacationStatus(vacation);
                                        const colors = STATUS_COLORS[status];
                                        const userName = vacation.user?.name || vacation.user?.fullName || 'Usuario';

                                        return (
                                            <TooltipProvider key={vacation.id}>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div
                                                            className={cn(
                                                                'text-xs px-1 py-0.5 rounded truncate cursor-pointer',
                                                                colors.bg,
                                                                'text-white'
                                                            )}
                                                        >
                                                            {userName.split(' ')[0]}
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <div className="text-sm">
                                                            <div className="font-medium">{userName}</div>
                                                            <div className="text-gray-400">
                                                                {vacation.dateRange || `${vacation.startDate} - ${vacation.endDate}`}
                                                            </div>
                                                            <div className="text-gray-400">
                                                                {vacation.daysRequested} día(s) • {vacation.statusLabel || vacation.status}
                                                            </div>
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        );
                                    })}

                                    {dayVacations.length > 3 && (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div className="text-xs text-center text-gray-500 cursor-pointer">
                                                        +{dayVacations.length - 3} más
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <div className="text-sm space-y-1">
                                                        {dayVacations.slice(3).map((vacation) => (
                                                            <div key={vacation.id}>
                                                                {vacation.user?.name || 'Usuario'}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

export default VacationCalendar;
