import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { format } from "date-fns";
import {
    FileStack,
    Eye,
    RefreshCw,
    X,
    Download,
    Loader2,
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
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useDocumentsStore } from "@/presentation/stores";
import { DocumentBatch } from "@/core/domain/entities/DocumentBatch";
import { getBatchStatusBadge, formatDateTime } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";

export function BatchesListPage() {
    const navigate = useNavigate();
    const { batches, batchesMeta, fetchBatches, batchesLoading } = useDocumentsStore();

    const [statusFilter, setStatusFilter] = useState<string>("all");
    const [dateRange, setDateRange] = useState<{ from: Date; to: Date | undefined } | undefined>();
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(10);
    const [isExporting, setIsExporting] = useState(false);

    useEffect(() => {
        loadBatches();
    }, [statusFilter, dateRange, currentPage, perPage]);

    const loadBatches = () => {
        fetchBatches({
            status: statusFilter !== "all" ? statusFilter : undefined,
            page: currentPage,
            perPage: perPage,
            dateFrom: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
            dateTo: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
        });
    };

    const clearFilters = () => {
        setStatusFilter("all");
        setDateRange(undefined);
        setCurrentPage(1);
    };

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
    };

    const handlePerPageChange = (newPerPage: number) => {
        setPerPage(newPerPage);
        setCurrentPage(1);
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportBatches({
                status: statusFilter !== 'all' ? statusFilter : undefined,
                start_date: dateRange?.from ? format(dateRange.from, 'yyyy-MM-dd') : undefined,
                end_date: dateRange?.to ? format(dateRange.to, 'yyyy-MM-dd') : undefined,
            });
            const filename = `lotes_carga_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            toast.error('Error al exportar lotes');
        } finally {
            setIsExporting(false);
        }
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
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        onClick={handleExport}
                        disabled={isExporting}
                    >
                        {isExporting ? (
                            <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                        ) : (
                            <Download className="w-4 h-4 mr-2" />
                        )}
                        Exportar
                    </Button>
                    <Button onClick={loadBatches} variant="outline" disabled={batchesLoading}>
                        <RefreshCw className={`w-4 h-4 mr-2 ${batchesLoading ? "animate-spin" : ""}`} />
                        Actualizar
                    </Button>
                </div>
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
                            initialDateFrom={dateRange?.from}
                            initialDateTo={dateRange?.to}
                            onUpdate={({ range }) => setDateRange(range)}
                            showCompare={false}
                            align="start"
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
                            <div className="w-full overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="whitespace-nowrap">ID</TableHead>
                                            <TableHead className="whitespace-nowrap">Tipo / Progreso</TableHead>
                                            <TableHead className="whitespace-nowrap">Período</TableHead>
                                            <TableHead className="whitespace-nowrap">Estado</TableHead>
                                            <TableHead className="whitespace-nowrap">Fecha</TableHead>
                                            <TableHead className="whitespace-nowrap text-center">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {batches.map((batch: DocumentBatch) => (
                                            <TableRow key={batch.id}>
                                                <TableCell className="font-mono whitespace-nowrap">#{batch.id}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium whitespace-nowrap">{batch.documentType?.displayName || "-"}</div>
                                                        <div className="text-xs text-[#64748B] truncate max-w-[180px]" title={batch.originalFilename}>
                                                            {batch.originalFilename}
                                                        </div>
                                                        <div className="flex items-center gap-2 mt-1">
                                                            <div className="w-20 h-2 bg-[#E2E8F0] rounded-full overflow-hidden flex-shrink-0">
                                                                <div
                                                                    className="h-full bg-[#2563EB] transition-all rounded-full"
                                                                    style={{ width: `${batch.progressPercentage || 0}%` }}
                                                                />
                                                            </div>
                                                            <span className="text-xs text-[#64748B]">
                                                                {batch.processedFiles}/{batch.totalFiles}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">{batch.period}</TableCell>
                                                <TableCell className="whitespace-nowrap">{getBatchStatusBadge(batch.status)}</TableCell>
                                                <TableCell className="text-[#64748B] text-sm whitespace-nowrap">
                                                    {formatDateTime(batch.createdAt)}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-center">
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
                            </div>

                            {/* Pagination */}
                            {batchesMeta && (
                                <PaginationControls
                                    currentPage={currentPage}
                                    totalPages={batchesMeta.lastPage || 1}
                                    total={batchesMeta.total}
                                    perPage={perPage}
                                    onPageChange={handlePageChange}
                                    onPerPageChange={handlePerPageChange}
                                    disabled={batchesLoading}
                                    className="mt-4 pt-4 border-t"
                                />
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default BatchesListPage;
