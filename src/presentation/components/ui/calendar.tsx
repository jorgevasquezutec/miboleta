"use client";

import * as React from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { DayPicker } from "react-day-picker";

import { cn } from "./utils";
import { buttonVariants } from "./button";

function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <>
      <style>{`
        .rdp-head_row, .rdp-row {
          display: grid !important;
          grid-template-columns: repeat(7, 1.75rem) !important;
          gap: 0.125rem !important;
        }
        .rdp-head_cell {
          width: 1.75rem !important;
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          font-size: 0.7rem !important;
        }
        .rdp-cell {
          width: 1.75rem !important;
        }
      `}</style>
      <DayPicker
        showOutsideDays={showOutsideDays}
        className={cn("p-2", className)}
        classNames={{
          months: "flex flex-col sm:flex-row gap-2",
          month: "flex flex-col gap-2",
          caption: "flex justify-center pt-1 relative items-center w-full",
          caption_label: "text-xs font-medium",
          nav: "flex items-center gap-1",
          nav_button: cn(
            buttonVariants({ variant: "outline" }),
            "size-6 bg-transparent p-0 opacity-50 hover:opacity-100",
          ),
          nav_button_previous: "absolute left-1",
          nav_button_next: "absolute right-1",
          table: "w-full border-collapse",
          head_row: "rdp-head_row",
          head_cell:
            "rdp-head_cell text-muted-foreground font-normal text-[0.65rem]",
          row: "rdp-row w-full mt-1",
          cell: cn(
            "rdp-cell relative p-0 text-center text-xs focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-blue-500 [&:has([aria-selected].day-range-end)]:rounded-r-md",
            props.mode === "range"
              ? "[&:has(>.day-range-end)]:rounded-r-md [&:has(>.day-range-start)]:rounded-l-md first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md"
              : "[&:has([aria-selected])]:rounded-md",
          ),
          day: cn(
            buttonVariants({ variant: "ghost" }),
            "size-7 p-0 font-normal text-xs aria-selected:opacity-100 aria-selected:text-white",
          ),
          day_range_start:
            "day-range-start aria-selected:bg-blue-600 aria-selected:text-white rounded-l-md",
          day_range_end:
            "day-range-end aria-selected:bg-blue-600 aria-selected:text-white rounded-r-md",
          day_selected:
            "bg-blue-600 text-white hover:bg-blue-700 hover:text-white focus:bg-blue-600 focus:text-white",
          day_today: "bg-blue-50 text-blue-900 font-semibold",
          day_outside:
            "day-outside text-muted-foreground aria-selected:text-white opacity-50",
          day_disabled: "text-muted-foreground opacity-50",
          day_range_middle:
            "aria-selected:bg-blue-500 aria-selected:text-white",
          day_hidden: "invisible",
          ...classNames,
        }}
        components={{
          IconLeft: ({ className, ...props }) => (
            <ChevronLeft className={cn("size-3", className)} {...props} />
          ),
          IconRight: ({ className, ...props }) => (
            <ChevronRight className={cn("size-3", className)} {...props} />
          ),
        }}
        {...props}
      />
    </>
  );
}

export { Calendar };
