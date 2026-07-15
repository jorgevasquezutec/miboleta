import { useState, useEffect, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { FileText, Search, Filter, Eye, Trash2, Download, Loader2 } from "lucide-react";
import { format, parse } from "date-fns";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import { Input } from "@/presentation/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/presentation/components/ui/table";
import { DateRangePicker, DateRange } from "@/presentation/components/ui/date-range-picker";
import { ConfirmDialog } from "@/presentation/components/shared/ConfirmDialog";
import { PaginationControls } from "@/presentation/components/shared/PaginationControls";
import { useUrlFilters, useTenantAwareEffect, useDocumentTitle } from "@/presentation/hooks";
import { useDocumentsStore } from "@/presentation/stores";
import { Document } from "@/core/domain/entities/Document";
import { useCan } from "@/presentation/hooks/useCan";
import { useTenantFilterStore } from "@/presentation/stores";
import { getDocumentStatusBadgeInline } from "@/presentation/utils";
import { reportsRepository } from "@/infrastructure/persistence/repositories";
import { toast } from "sonner";

export function DocumentsListPage() {
    useDocumentTitle('Documentos');
    const navigate = useNavigate();
    // Permiso de la Matriz de Accesos. Antes: user?.role === "admin" (rol
    // GLOBAL), que además dejaba fuera a root y a admin_tenant.
    const canDeleteDocument = useCan("documents.delete");
    const {
        documents,
        documentTypes,
        fetchDocuments,
        fetchDocumentTypes,
        deleteDocument,
        isLoading,
        total,
        totalPages,
    } = useDocumentsStore();

    // URL-synced filters
    const { filters, setFilters, resetFilters } = useUrlFilters({
        defaultValues: {
            search: '',
            status: 'all',
            doc_type_id: '',
            date_from: '',
            date_to: '',
            page: 1,
            per_page: 10,
        }
    });

    // Local state for search input (debounce)
    const [searchInput, setSearchInput] = useState(filters.search);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<number | null>(null);
    const [isExporting, setIsExporting] = useState(false);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Filtro de empresa del navbar (header X-Tenant-Ids). Root usa el
    // TenantSwitcher (una empresa o "todas"); no-root usa el
    // TenantMultiSwitcher (una o varias). Guardamos la key previa para
    // poder resetear la página a 1 cuando cambia, sin doble fetch (ver
    // efecto de abajo).
    // Se copia antes de ordenar: .sort() ordena IN-PLACE y sin el spread este
    // selector mutaba state.filter.tenantIds del store durante el render.
    const tenantFilterKey = useTenantFilterStore(
        (state) => [...state.filter.tenantIds].sort().join(',')
    );
    const prevTenantFilterKeyRef = useRef(tenantFilterKey);

    // Parse dates from URL
    const dateRange: DateRange | undefined = filters.date_from ? {
        from: parse(filters.date_from, 'yyyy-MM-dd', new Date()),
        to: filters.date_to ? parse(filters.date_to, 'yyyy-MM-dd', new Date()) : undefined,
    } : undefined;

    // Debounce search
    useEffect(() => {
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }
        debounceTimer.current = setTimeout(() => {
            if (searchInput !== filters.search) {
                setFilters({ search: searchInput, page: 1 });
            }
        }, 500);
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [searchInput, filters.search, setFilters]);

    // Semilla inicial del input de búsqueda desde la URL. Solo al montar
    // A PROPÓSITO: con `filters.search` en las dependencias, el valor que el
    // debounce escribe en la URL 500 ms después volvería a entrar aquí y
    // pisaría lo que el usuario haya seguido tecleando entre medias.
    useEffect(() => {
        setSearchInput(filters.search);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const fetchTypes = async () => {
            await fetchDocumentTypes();
        };
        fetchTypes();
    }, [fetchDocumentTypes]);

    // Fetch documents when filters change
    // ✅ MIGRATED: Now automatically refetches when tenant filter changes
    // tenant_id removed - el modelo Document aplica el header X-Tenant-Ids
    // automáticamente (TenantFilterScope), que root controla desde el
    // navbar (TenantSwitcher) y no-root desde el TenantMultiSwitcher. No
    // hay filtro local de empresa en esta página.
    //
    // Si cambia la selección de empresa (root o no-root) mientras estamos
    // en una página > 1, reseteamos a la página 1 primero (sin fetch) y
    // dejamos que ese cambio de `filters.page` dispare el fetch real en la
    // siguiente ejecución del efecto, evitando pedir la página vieja con
    // la empresa nueva.
    useTenantAwareEffect(() => {
        const tenantChanged = prevTenantFilterKeyRef.current !== tenantFilterKey;
        prevTenantFilterKeyRef.current = tenantFilterKey;

        if (tenantChanged && filters.page !== 1) {
            setFilters({ page: 1 });
            return;
        }

        fetchDocuments({
            page: filters.page,
            perPage: filters.per_page,
            search: filters.search || undefined,
            status: filters.status !== 'all' ? (filters.status as Document['status']) : undefined,
            docTypeId: filters.doc_type_id ? parseInt(filters.doc_type_id) : undefined,
            dateFrom: filters.date_from || undefined,
            dateTo: filters.date_to || undefined,
        });
    }, [filters.page, filters.per_page, filters.search, filters.status, filters.doc_type_id, filters.date_from, filters.date_to, fetchDocuments, tenantFilterKey]);

    const handleSearch = () => {
        setFilters({ page: 1 });
    };

    const handleResetFilters = () => {
        setSearchInput('');
        resetFilters();
    };

    const handleStatusChange = (value: string) => {
        setFilters({ status: value, page: 1 });
    };

    const handleDocTypeChange = (value: string) => {
        setFilters({ doc_type_id: value === 'all' ? '' : value, page: 1 });
    };

    const handleDateRangeChange = (values: { range: DateRange }) => {
        setFilters({
            date_from: values.range.from ? format(values.range.from, 'yyyy-MM-dd') : '',
            date_to: values.range.to ? format(values.range.to, 'yyyy-MM-dd') : '',
            page: 1,
        });
    };

    const handlePageChange = (page: number) => {
        setFilters({ page });
    };

    const handlePerPageChange = (perPage: number) => {
        setFilters({ per_page: perPage, page: 1 });
    };

    const handleDeleteClick = (id: number) => {
        setDocumentToDelete(id);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = async () => {
        if (documentToDelete) {
            try {
                await deleteDocument(documentToDelete);
            } catch (error) {
                console.error("Error deleting document:", error);
            } finally {
                setDeleteDialogOpen(false);
                setDocumentToDelete(null);
            }
        }
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await reportsRepository.exportDocuments({
                search: filters.search || undefined,
                status: filters.status !== 'all' ? filters.status : undefined,
                document_type: filters.doc_type_id || undefined,
                // tenant_id removed - backend usa el header X-Tenant-Ids (TenantFilterScope)
                start_date: filters.date_from || undefined,
                end_date: filters.date_to || undefined,
            });
            const filename = `documentos_${new Date().toISOString().split('T')[0]}.xlsx`;
            reportsRepository.downloadBlob(blob, filename);
            toast.success('Exportación completada');
        } catch (error) {
            toast.error('Error al exportar documentos');
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <div className="space-y-4 sm:space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div>
                    <h1 className="flex items-center gap-2 text-lg sm:text-xl font-bold">
                        <FileText className="w-5 h-5 sm:w-6 sm:h-6 text-[#2563EB]" />
                        Buscador de Documentos
                    </h1>
                    <p className="text-[#64748B] text-sm sm:text-base">
                        Busca y gestiona todos los documentos del sistema
                    </p>
                </div>
                <Button
                    variant="outline"
                    className="h-9 sm:h-10 px-3 sm:px-4 w-full sm:w-auto"
                    onClick={handleExport}
                    disabled={isExporting}
                >
                    {isExporting ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                        <Download className="w-4 h-4" />
                    )}
                    <span className="ml-2">Exportar</span>
                </Button>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-wrap gap-3 items-end">
                        {/* Date Range Picker */}
                        <div className="min-w-[200px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Rango de fechas
                            </label>
                            <DateRangePicker
                                initialDateFrom={dateRange?.from}
                                initialDateTo={dateRange?.to}
                                onUpdate={handleDateRangeChange}
                                showCompare={false}
                                align="start"
                                compact
                            />
                        </div>

                        {/* Nota: sin filtro local de empresa. Para root, el navbar
                            (TenantSwitcher) es el único control de empresa y ya
                            filtra la lista vía el header X-Tenant-Ids. */}

                        {/* Document Type Filter */}
                        <div className="min-w-[160px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Tipo documento
                            </label>
                            <Select value={filters.doc_type_id || 'all'} onValueChange={handleDocTypeChange}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    {documentTypes.map((type) => (
                                        <SelectItem key={type.id} value={type.id.toString()}>
                                            {type.displayName}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Status Filter */}
                        <div className="min-w-[140px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Estado
                            </label>
                            <Select value={filters.status} onValueChange={handleStatusChange}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="Todos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos</SelectItem>
                                    <SelectItem value="pending">Pendiente Firma</SelectItem>
                                    <SelectItem value="signed">Firmado</SelectItem>
                                    <SelectItem value="active">Disponible</SelectItem>
                                    <SelectItem value="orphan">Huérfano</SelectItem>
                                    <SelectItem value="expired">Expirado</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Search */}
                        <div className="flex-1 min-w-[200px]">
                            <label className="text-xs font-medium mb-1 block text-gray-600">
                                Buscar usuario
                            </label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
                                <Input
                                    placeholder="Nombre, apellido o DNI..."
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                                    className="pl-8 h-9"
                                />
                            </div>
                        </div>

                        {/* Limpiar filtros */}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleResetFilters}
                            className="h-9 whitespace-nowrap"
                        >
                            <Filter className="w-3.5 h-3.5 mr-1.5" />
                            Limpiar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Results */}
            <Card>
                <CardHeader>
                    <CardTitle>
                        Resultados {total > 0 && `(${total} documentos)`}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {isLoading && documents.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            Cargando documentos...
                        </div>
                    ) : documents.length === 0 ? (
                        <div className="py-12 text-center text-[#64748B]">
                            <FileText className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <p>No se encontraron documentos</p>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="min-w-[180px]">Apellido y Nombre / DNI</TableHead>
                                            <TableHead className="min-w-[180px]">Tipo de documento / Período</TableHead>
                                            <TableHead className="min-w-[120px] hidden sm:table-cell">Fecha de subida</TableHead>
                                            <TableHead className="min-w-[100px]">Estado</TableHead>
                                            <TableHead className="min-w-[80px]">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {documents.map((doc) => (
                                            <TableRow key={doc.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {doc.user?.name} {doc.user?.lastName}
                                                        </div>
                                                        <div className="text-sm text-[#64748B]">
                                                            {doc.user?.documentText || doc.employeeDocumentNumber}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {doc.documentType?.displayName || "-"}
                                                        </div>
                                                        <div className="text-sm text-[#64748B]">{doc.period}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-sm text-[#64748B] hidden sm:table-cell">
                                                    {new Date(doc.createdAt).toLocaleString('es-PE', {
                                                        day: '2-digit',
                                                        month: '2-digit',
                                                        year: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    })}
                                                </TableCell>
                                                <TableCell>{getDocumentStatusBadgeInline(doc.status)}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => navigate(`/viewer?id=${doc.id}`)}
                                                            title="Ver documento"
                                                        >
                                                            <Eye className="w-4 h-4 text-[#2563EB]" />
                                                        </Button>
                                                        {canDeleteDocument && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => handleDeleteClick(doc.id)}
                                                                title="Eliminar documento"
                                                            >
                                                                <Trash2 className="w-4 h-4 text-red-600" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Pagination */}
                            <PaginationControls
                                currentPage={filters.page}
                                totalPages={totalPages}
                                total={total}
                                perPage={filters.per_page}
                                onPageChange={handlePageChange}
                                onPerPageChange={handlePerPageChange}
                                disabled={isLoading}
                                className="mt-4 pt-4 border-t"
                            />
                        </>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                onConfirm={handleDeleteConfirm}
                title="Eliminar Documento"
                description="¿Estás seguro de que deseas eliminar este documento? Esta acción no se puede deshacer."
                confirmText="Eliminar"
                cancelText="Cancelar"
            />
        </div>
    );
}

export default DocumentsListPage;
