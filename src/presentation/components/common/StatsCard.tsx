import { LucideIcon } from "lucide-react";
import { Card, CardContent } from "@/presentation/components/ui/card";

interface StatsCardProps {
  title: string;
  value: string | number;
  icon: LucideIcon;
  trend?: {
    value: string;
    isPositive: boolean;
  };
  color?: string;
}

export function StatsCard({ title, value, icon: Icon, trend, color = "#2563EB" }: StatsCardProps) {
  return (
    <Card className="hover:shadow-md transition-shadow duration-200 overflow-hidden">
      <CardContent className="p-4 sm:p-6">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <p className="text-[#64748B] text-sm sm:text-base mb-1 sm:mb-2 truncate">{title}</p>
            <h2 className="text-xl sm:text-2xl font-bold mb-1 sm:mb-2">{value}</h2>
            {trend && (
              <p className={`text-sm ${trend.isPositive ? "text-[#10B981]" : "text-[#EF4444]"}`}>
                {trend.isPositive ? "↑" : "↓"} {trend.value}
              </p>
            )}
          </div>
          <div
            className="w-10 h-10 sm:w-12 sm:h-12 rounded-lg flex items-center justify-center flex-shrink-0"
            style={{ backgroundColor: `${color}20` }}
          >
            <Icon className="w-5 h-5 sm:w-6 sm:h-6" style={{ color }} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export default StatsCard;
