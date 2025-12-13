import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { DateRange } from "react-day-picker";
import { format } from "date-fns";
import {
    FileStack,
    Eye,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    X,
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/presentation/components/ui/table";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import { DateRangePicker } from "@/presentation/components/ui/date-range-picker";
import { useDocumentsStore } from "@/presentation/stores";
import { DocumentBatch } from "@/core/domain/entities/DocumentBatch";
import { getBatchStatusBadge, formatDateTime } from "@/presentation/utils";

export function BatchesListPage() {
    const navigate = useNavigate();
    const { batches, batchesMeta, fetchBatches, batchesLoading } = useDocumentsStore();

    const [statusFilter, setStatusFilter] = useState<string>("all");
    const [dateRange, setDateRange] = useState<DateRange | undefined>();
    const [currentPage, setCurrentPage] = useState(1);

    useEffect(() => {
        loadBatches();
    }, [statusFilter, dateRange, currentPage]);

    const loadBatches = () => {
        fetchBatches({
            status: statusFilter !== "all" ? statusFilter : undefined,
            page: currentPage,
            perPage: 10,
            dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
            dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
        });
    };

    const clearFilters = () => {
        setStatusFilter("all");
        setDateRange(undefined);
        setCurrentPage(1);
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="flex items-center gap-2">
                        <FileStack className="w-6 h-6 text-[#2563EB]" />
                        Lotes de Carga
                    </h1>
                    <p className="text-[#64748B]">
                        Historial de cargas masivas de documentos
                    </p>
                </div>
                <Button onClick={loadBatches} variant="outline" disabled={batchesLoading}>
                    <RefreshCw className={`w-4 h-4 mr-2 ${batchesLoading ? "animate-spin" : ""}`} />
                    Actualizar
                </Button>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">Estado:</span>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="pending">Pendiente</SelectItem>
                                    <SelectItem value="processing">Procesando</SelectItem>
                                    <SelectItem value="completed">Completado</SelectItem>
                                    <SelectItem value="failed">Fallido</SelectItem>
                                    <SelectItem value="partial">Parcial</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <DateRangePicker
                            dateRange={dateRange}
                            onDateRangeChange={setDateRange}
                        />
                        {(statusFilter !== "all" || dateRange) && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="w-4 h-4 mr-1" />
                                Limpiar filtros
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Historial de Lotes</CardTitle>
                </CardHeader>
                <CardContent>
                    {batchesLoading && batches.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            <RefreshCw className="w-8 h-8 mx-auto mb-4 animate-spin" />
                            <p>Cargando lotes...</p>
                        </div>
                    ) : batches.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            <FileStack className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <p>No hay lotes de carga registrados</p>
                        </div>
                    ) : (
                        <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID</TableHead>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Período</TableHead>
                                        <TableHead>Archivo</TableHead>
                                        <TableHead>Progreso</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Acciones</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {batches.map((batch: DocumentBatch) => (
                                        <TableRow key={batch.id}>
                                            <TableCell className="font-mono">#{batch.id}</TableCell>
                                            <TableCell>{batch.documentType?.displayName || "-"}</TableCell>
                                            <TableCell>{batch.period}</TableCell>
                                            <TableCell className="max-w-[200px] truncate" title={batch.originalFilename}>
                                                {batch.originalFilename}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="w-20 h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full bg-[#2563EB] transition-all"
                                                            style={{ width: `${batch.progressPercentage || 0}%` }}
                                                        />
                                                    </div>
                                                    <span className="text-sm text-[#64748B]">
                                                        {batch.processedFiles}/{batch.totalFiles}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{getBatchStatusBadge(batch.status)}</TableCell>
                                            <TableCell className="text-[#64748B] text-sm">
                                                {formatDateTime(batch.createdAt)}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => navigate(`/batches/${batch.id}`)}
                                                >
                                                    <Eye className="w-4 h-4 mr-1" />
                                                    Ver
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Pagination */}
                            {batchesMeta && (
                                <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                    <div className="text-sm text-[#64748B]">
                                        Mostrando página {batchesMeta.currentPage} de {batchesMeta.lastPage} ({batchesMeta.total} lotes)
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={currentPage <= 1}
                                            onClick={() => setCurrentPage(currentPage - 1)}
                                        >
                                            <ChevronLeft className="w-4 h-4" />
                                        </Button>
                                        <span className="px-3 py-1 text-sm">
                                            {currentPage}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={currentPage >= batchesMeta.lastPage}
                                            onClick={() => setCurrentPage(currentPage + 1)}
                                        >
                                            <ChevronRight className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default BatchesListPage;
