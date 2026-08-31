import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { format, parse } from "date-fns";
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
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useDocumentsStore } from "@/presentation/stores";
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { DocumentBatch } from "@/core/domain/entities/DocumentBatch";
import { getBatchStatusBadge, formatDateTime } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";
import { showApiError } from "@/presentation/utils/showApiError";

export function BatchesListPage() {
    useDocumentTitle('Historial de Carga');
    const navigate = useNavigate();

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            status: 'all',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 10,
        }
    });

    const [isExporting, setIsExporting] = useState(false);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

    const { batches, batchesMeta, fetchBatches, batchesLoading } = useDocumentsStore();

    // Fetch batches when filters change
    // ✅ MIGRATED: Now reacts automatically to tenant filter changes
    useTenantAwareEffect(() => {
        fetchBatches({
            status: filters.status !== 'all' ? filters.status : undefined,
            page: filters.page,
            perPage: filters.per_page,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
        });
    }, [filters.status, filters.date_from, filters.date_to, filters.page, filters.per_page, fetchBatches]);

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
            page: 1,
        });
    };

    const clearFilters = () => {
        resetFilters();
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
    };

    const handlePerPageChange = (newPerPage: number) => {
        setFilters({ per_page: newPerPage, page: 1 });
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportBatches({
                status: filters.status !== 'all' ? filters.status : undefined,
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
            });
            const filename = `lotes_carga_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            showApiError(error, 'Error al exportar lotes');
        } finally {
            setIsExporting(false);
        }
    };

    const loadBatches = () => {
        fetchBatches({
            status: filters.status !== 'all' ? filters.status : undefined,
            page: filters.page,
            perPage: filters.per_page,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
        });
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="flex items-center gap-2">
                        <FileStack className="w-6 h-6 text-[#2563EB]" />
                        Historial de Carga
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
                            <Select value={filters.status} onValueChange={handleStatusChange}>
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
                            onUpdate={handleDateRangeChange}
                            showCompare={false}
                            align="start"
                            compact
                        />
                        {(filters.status !== 'all' || filters.date_from) && (
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
                            <PaginationControls
                                currentPage={batchesMeta?.currentPage || filters.page}
                                totalPages={batchesMeta?.lastPage || 1}
                                total={batchesMeta?.total || 0}
                                perPage={batchesMeta?.perPage || filters.per_page}
                                onPageChange={handlePageChange}
                                onPerPageChange={handlePerPageChange}
                                disabled={batchesLoading}
                                className="mt-4 pt-4 border-t"
                            />
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default BatchesListPage;
