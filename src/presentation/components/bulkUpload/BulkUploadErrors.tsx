import { Card, CardHeader, CardTitle, CardContent } from '@/presentation/components/ui/card';
import { AlertTriangle } from 'lucide-react';

interface ErrorDetail {
    nombre: string;
    apellido: string;
    email: string;
    documento: string;
    error: string;
    chunk?: number;
    timestamp?: string;
}

interface BulkUploadErrorsProps {
    errors: ErrorDetail[];
}

export function BulkUploadErrors({ errors }: BulkUploadErrorsProps) {
    if (!errors || errors.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5 text-red-600" />
                    <span>Errores Encontrados ({errors.length})</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left py-2 px-3 font-medium text-gray-700">Nombre</th>
                                <th className="text-left py-2 px-3 font-medium text-gray-700">Email</th>
                                <th className="text-left py-2 px-3 font-medium text-gray-700">Documento</th>
                                <th className="text-left py-2 px-3 font-medium text-gray-700">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {errors.map((error, index) => (
                                <tr key={index} className="border-b hover:bg-gray-50">
                                    <td className="py-2 px-3">
                                        {error.nombre} {error.apellido}
                                    </td>
                                    <td className="py-2 px-3">{error.email}</td>
                                    <td className="py-2 px-3">{error.documento}</td>
                                    <td className="py-2 px-3">
                                        <span className="text-red-600 text-xs">
                                            {error.error}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
