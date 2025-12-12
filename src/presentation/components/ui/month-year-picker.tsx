import * as React from "react";
import { ChevronLeft, ChevronRight, Calendar } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/presentation/components/ui/popover";
import { cn } from "@/presentation/components/ui/utils";

interface MonthYearPickerProps {
    value?: string; // Format: YYYY-MM
    onChange: (value: string) => void;
    placeholder?: string;
    minYear?: number;
    maxYear?: number;
    className?: string;
    disabled?: boolean;
}

const MONTHS = [
    { value: "01", label: "Enero" },
    { value: "02", label: "Febrero" },
    { value: "03", label: "Marzo" },
    { value: "04", label: "Abril" },
    { value: "05", label: "Mayo" },
    { value: "06", label: "Junio" },
    { value: "07", label: "Julio" },
    { value: "08", label: "Agosto" },
    { value: "09", label: "Septiembre" },
    { value: "10", label: "Octubre" },
    { value: "11", label: "Noviembre" },
    { value: "12", label: "Diciembre" },
];

export function MonthYearPicker({
    value,
    onChange,
    placeholder = "Seleccionar período...",
    minYear = 2020,
    maxYear = new Date().getFullYear() + 1,
    className,
    disabled = false,
}: MonthYearPickerProps) {
    const [open, setOpen] = React.useState(false);
    const [displayYear, setDisplayYear] = React.useState(() => {
        if (value) {
            return parseInt(value.split("-")[0]);
        }
        return new Date().getFullYear();
    });

    const selectedYear = value ? parseInt(value.split("-")[0]) : null;
    const selectedMonth = value ? value.split("-")[1] : null;

    const handleMonthSelect = (month: string) => {
        onChange(`${displayYear}-${month}`);
        setOpen(false);
    };

    const handlePrevYear = () => {
        if (displayYear > minYear) {
            setDisplayYear(displayYear - 1);
        }
    };

    const handleNextYear = () => {
        if (displayYear < maxYear) {
            setDisplayYear(displayYear + 1);
        }
    };

    const formatDisplayValue = () => {
        if (!value) return null;
        const [year, month] = value.split("-");
        const monthName = MONTHS.find((m) => m.value === month)?.label || month;
        return `${monthName} ${year}`;
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        "w-full justify-start text-left font-normal",
                        !value && "text-muted-foreground",
                        className
                    )}
                >
                    <Calendar className="mr-2 h-4 w-4" />
                    {formatDisplayValue() || placeholder}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[320px] p-0" align="start">
                <div className="p-3">
                    {/* Year Navigation */}
                    <div className="flex items-center justify-between mb-4">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-7 w-7"
                            onClick={handlePrevYear}
                            disabled={displayYear <= minYear}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="font-semibold text-lg">{displayYear}</span>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-7 w-7"
                            onClick={handleNextYear}
                            disabled={displayYear >= maxYear}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>

                    {/* Months Grid */}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '8px' }}>
                        {MONTHS.map((month) => {
                            const isSelected =
                                selectedYear === displayYear && selectedMonth === month.value;
                            const isFuture =
                                displayYear === new Date().getFullYear() &&
                                parseInt(month.value) > new Date().getMonth() + 1;

                            return (
                                <button
                                    key={month.value}
                                    type="button"
                                    onClick={() => handleMonthSelect(month.value)}
                                    className={cn(
                                        "h-9 px-2 rounded-md text-sm font-medium transition-colors",
                                        "hover:bg-accent hover:text-accent-foreground",
                                        isSelected && "bg-[#2563EB] text-white hover:bg-[#1E40AF]",
                                        !isSelected && "bg-transparent",
                                        isFuture && "opacity-50"
                                    )}
                                >
                                    {month.label.slice(0, 3)}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
