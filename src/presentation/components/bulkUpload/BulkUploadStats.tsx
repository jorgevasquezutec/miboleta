import { Card, CardContent } from '@/presentation/components/ui/card';
import { Users, UserPlus, AlertTriangle } from 'lucide-react';

interface BulkUploadStatsProps {
    totalRows: number;
    createdUsers: number;
    failedRows: number;
}

export function BulkUploadStats({
    totalRows,
    createdUsers,
    failedRows,
}: BulkUploadStatsProps) {
    const stats = [
        {
            label: 'Total Filas',
            value: totalRows,
            icon: Users,
            color: 'text-blue-600',
            bgColor: 'bg-blue-100',
        },
        {
            label: 'Usuarios Creados',
            value: createdUsers,
            icon: UserPlus,
            color: 'text-green-600',
            bgColor: 'bg-green-100',
        },
        {
            label: 'Errores',
            value: failedRows,
            icon: AlertTriangle,
            color: 'text-red-600',
            bgColor: 'bg-red-100',
        },
    ];

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {stats.map((stat) => {
                const Icon = stat.icon;
                return (
                    <Card key={stat.label}>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{stat.label}</p>
                                    <p className="text-3xl font-bold mt-2">{stat.value}</p>
                                </div>
                                <div className={`p-3 rounded-full ${stat.bgColor}`}>
                                    <Icon className={`h-6 w-6 ${stat.color}`} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
