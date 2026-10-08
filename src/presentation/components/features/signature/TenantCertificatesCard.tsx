import { useEffect, useRef, useState } from 'react';
import { AlertTriangle, Loader2, Trash2, Upload, X } from 'lucide-react';
import { toast } from 'sonner';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/presentation/components/ui/card';
import { Badge } from '@/presentation/components/ui/badge';
import { Button } from '@/presentation/components/ui/button';
import { Input } from '@/presentation/components/ui/input';
import { Label } from '@/presentation/components/ui/label';
import { Switch } from '@/presentation/components/ui/switch';
import { Alert, AlertDescription } from '@/presentation/components/ui/alert';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/presentation/components/ui/table';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/presentation/components/ui/tooltip';
import { PaginationControls } from '@/presentation/components/shared/PaginationControls';
import { ConfirmDialog } from '@/presentation/components/shared/ConfirmDialog';
import { useDebounce } from '@/presentation/hooks/useDebounce';
import { useSignatureSettingsStore } from '@/presentation/stores';
import { formatDate } from '@/presentation/utils';
import { getErrorMessage } from '@/infrastructure/http/apiClient';
import { TenantCertificateStatus } from '@/core/domain/entities';
import { TenantCertificateMutationResult } from '@/core/domain/repositories/ISignatureSettingsRepository';
import { TenantCertificateUploadDialog } from './TenantCertificateUploadDialog';
import { getExpiryState } from './certificateExpiry';
import { AMBER_BADGE, SourceBadge, WARNING_LABELS } from './certificateUi';

/**
 * Tarjeta "Certificados por empresa" (solo root, dentro de SignatureSettingsPage).
 * Lista cada empresa con su certificado efectivo (propio, global o ninguno) y
 * permite cargar/reemplazar/eliminar el certificado propio.
 */
export function TenantCertificatesCard() {
  const {
    tenantCertificates,
    tenantSummary,
    isLoadingTenants,
    savingTenantId,
    tenantError,
    lastTenantFilters,
    fetchTenantCertificates,
    deleteTenantCertificate,
  } = useSignatureSettingsStore();

  const [search, setSearch] = useState(lastTenantFilters.search ?? '');
  const [onlyWarnings, setOnlyWarnings] = useState(!!lastTenantFilters.onlyWarnings);
  const debouncedSearch = useDebounce(search, 400);

  const [uploadTarget, setUploadTarget] = useState<TenantCertificateStatus | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<TenantCertificateStatus | null>(null);
  const [mismatchNotice, setMismatchNotice] = useState<string | null>(null);

  // Evita un fetch duplicado al montar (la página ya llama fetchTenantCertificates()).
  const isFirstRender = useRef(true);
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    // Cambiar búsqueda o filtro vuelve a la página 1 (se conserva per_page)
    fetchTenantCertificates({
      search: debouncedSearch.trim() || undefined,
      onlyWarnings,
      page: 1,
      perPage: useSignatureSettingsStore.getState().lastTenantFilters.perPage,
    });
  }, [debouncedSearch, onlyWarnings, fetchTenantCertificates]);

  const goToPage = (page: number) => fetchTenantCertificates({ ...lastTenantFilters, page });
  const changePerPage = (perPage: number) => fetchTenantCertificates({ ...lastTenantFilters, perPage, page: 1 });

  const handleUploaded = (result: TenantCertificateMutationResult) => {
    const { item } = result;
    if (item.rucMismatch) {
      setMismatchNotice(
        `Aviso: el RUC del certificado de ${item.tenantName} (${item.certificate?.certificateRuc ?? '-'}) no coincide con el RUC de la empresa (${item.tenantRuc ?? '-'}).`
      );
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      const result = await deleteTenantCertificate(deleteTarget.tenantId);
      toast.success(result.message);
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  };

  const withWarnings = tenantSummary?.withWarnings ?? 0;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Certificados por empresa</CardTitle>
        <CardDescription>
          Cada empresa puede tener su propio certificado. Si no tiene uno, sus documentos se firman con el
          certificado global de la plataforma.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {tenantSummary && (
          <div className="flex items-center gap-2 text-sm text-[#64748B]">
            <span>{tenantSummary.withOwnCertificate} con certificado propio</span>
            <span>·</span>
            {withWarnings > 0 ? (
              <Badge className={AMBER_BADGE}>{withWarnings} con aviso</Badge>
            ) : (
              <span>{withWarnings} con aviso</span>
            )}
          </div>
        )}

        <div className="flex flex-col sm:flex-row sm:items-center gap-3">
          <Input
            className="sm:max-w-xs"
            placeholder="Buscar empresa o RUC"
            aria-label="Buscar empresa o RUC"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <div className="flex items-center gap-2">
            <Switch id="only-warnings" checked={onlyWarnings} onCheckedChange={setOnlyWarnings} />
            <Label htmlFor="only-warnings">Solo con avisos</Label>
          </div>
        </div>

        {tenantError && (
          <Alert variant="destructive">
            <AlertDescription>{tenantError}</AlertDescription>
          </Alert>
        )}

        {mismatchNotice && (
          <Alert className="border-amber-300 bg-amber-50">
            <AlertTriangle className="w-4 h-4 text-amber-600" />
            <AlertDescription className="text-sm text-amber-900 flex items-start justify-between gap-2">
              <span>{mismatchNotice}</span>
              <button
                type="button"
                aria-label="Cerrar aviso"
                className="shrink-0"
                onClick={() => setMismatchNotice(null)}
              >
                <X className="w-4 h-4" />
              </button>
            </AlertDescription>
          </Alert>
        )}

        <div className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Empresa</TableHead>
                <TableHead>Certificado</TableHead>
                <TableHead>Datos del certificado</TableHead>
                <TableHead>Vence</TableHead>
                <TableHead>Avisos</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoadingTenants && tenantCertificates.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-[#64748B]">
                    <Loader2 className="w-4 h-4 animate-spin inline mr-2" />
                    Cargando empresas...
                  </TableCell>
                </TableRow>
              ) : tenantCertificates.length === 0 && tenantError ? (
                // El Alert de arriba es el único mensaje: no afirmar que no hay empresas
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-[#64748B]">
                    No se pudo cargar la lista.
                  </TableCell>
                </TableRow>
              ) : tenantCertificates.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-[#64748B]">
                    {onlyWarnings ? 'Ninguna empresa tiene avisos.' : 'No hay empresas registradas.'}
                  </TableCell>
                </TableRow>
              ) : (
                tenantCertificates.map((item) => {
                  const expiry = getExpiryState(item.certificate?.certificateExpiresAt);
                  const busy = savingTenantId === item.tenantId;
                  return (
                    <TableRow
                      key={item.tenantId}
                      className={item.warnings.length > 0 ? 'border-l-4 border-l-amber-400' : undefined}
                    >
                      <TableCell>
                        <div className="font-medium">{item.tenantName}</div>
                        <div className="text-xs text-[#64748B]">{item.tenantRuc || '-'}</div>
                      </TableCell>
                      <TableCell>
                        <SourceBadge source={item.certificateSource} />
                      </TableCell>
                      <TableCell className="max-w-[220px]">
                        <div>{item.certificate?.certificateRuc || '-'}</div>
                        {item.certificate?.certificateOrganization && (
                          <div
                            className="text-xs text-[#64748B] truncate"
                            title={item.certificate.certificateOrganization}
                          >
                            {item.certificate.certificateOrganization}
                          </div>
                        )}
                        {item.certificate?.tsaUrl && (
                          <div
                            className="text-xs text-[#64748B] truncate"
                            title={`TSA propia: ${item.certificate.tsaUrl}`}
                          >
                            TSA propia: {item.certificate.tsaUrl}
                          </div>
                        )}
                      </TableCell>
                      <TableCell
                        className={
                          expiry === 'expired'
                            ? 'whitespace-nowrap text-red-600 font-medium'
                            : expiry === 'expiring_soon'
                              ? 'whitespace-nowrap text-amber-600 font-medium'
                              : 'whitespace-nowrap'
                        }
                      >
                        {formatDate(item.certificate?.certificateExpiresAt ?? null)}
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {item.warnings.map((warning) => (
                            <Tooltip key={warning.code}>
                              <TooltipTrigger asChild>
                                <Badge className={AMBER_BADGE}>
                                  <AlertTriangle />
                                  {WARNING_LABELS[warning.code]}
                                </Badge>
                              </TooltipTrigger>
                              <TooltipContent>{warning.message}</TooltipContent>
                            </Tooltip>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            className="gap-1"
                            onClick={() => setUploadTarget(item)}
                            disabled={busy}
                          >
                            <Upload className="w-3.5 h-3.5" />
                            {item.hasOwnCertificate ? 'Reemplazar' : 'Cargar'}
                          </Button>
                          {item.hasOwnCertificate && (
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                              onClick={() => setDeleteTarget(item)}
                              disabled={busy}
                              aria-label="Eliminar"
                              title="Eliminar certificado"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </div>

        {tenantSummary && tenantSummary.total > 0 && (
          <PaginationControls
            currentPage={tenantSummary.currentPage}
            totalPages={tenantSummary.lastPage}
            total={tenantSummary.total}
            perPage={tenantSummary.perPage}
            onPageChange={goToPage}
            onPerPageChange={changePerPage}
            disabled={isLoadingTenants}
            perPageOptions={[10, 25, 50, 100]}
          />
        )}
      </CardContent>

      <TenantCertificateUploadDialog
        tenant={uploadTarget}
        open={!!uploadTarget}
        onOpenChange={(open) => {
          if (!open) setUploadTarget(null);
        }}
        onUploaded={handleUploaded}
      />

      <ConfirmDialog
        open={!!deleteTarget}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
        title="Eliminar certificado"
        description={`¿Eliminar el certificado de ${deleteTarget?.tenantName ?? ''}? Sus documentos se firmarán con el certificado global.`}
        confirmText="Eliminar"
        variant="destructive"
        onConfirm={handleDelete}
      />
    </Card>
  );
}
