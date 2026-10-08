import { useState, useEffect, useMemo } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useSearchParams, useNavigate } from "react-router-dom";
import { ArrowLeft, ChevronDown, Download, FileText, CheckCircle, Info, Loader2, AlertCircle, ShieldCheck, Clock, RefreshCw } from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent } from "@/presentation/components/ui/card";
import { Badge } from "@/presentation/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/presentation/components/ui/collapsible";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/presentation/components/ui/tooltip";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { toast } from "sonner";
import { useDocumentsStore, useAuthStore } from "@/presentation/stores";
import { PDFViewer } from "@/presentation/components/shared/PDFViewer";
import { DocumentSignatureModal } from "@/presentation/components/features/documents/DocumentSignatureModal";
import { VerifySignatureModal } from "@/presentation/components/features/documents/VerifySignatureModal";
import { getDocumentStatusBadge, formatDate, formatDateTime, formatSignerDetails } from "@/presentation/utils";
import { getErrorMessage } from "@/infrastructure/http/apiClient";
import { useCan } from "@/presentation/hooks/useCan";

interface DocumentViewerViewProps {
  onBack?: () => void;
}

export function DocumentViewerView({ onBack }: DocumentViewerViewProps) {
  useDocumentTitle('Visor de Documento');
  const navigate = useNavigate();

  const handleBack = () => {
    if (onBack) {
      onBack();
    } else {
      navigate(-1); // Go back to previous page
    }
  };
  const [searchParams] = useSearchParams();
  const documentId = searchParams.get("id");

  const { user } = useAuthStore();
  const {
    currentDocument,
    fetchDocumentById,
    isLoading,
    error,
    signatureTermsAccepted,
    checkSignatureTerms,
    acceptSignatureTerms,
    requestSignatureCode,
    signDocument,
    pollDigitalSignature,
    retryDigitalSignature,
    signatureVerification,
    signatureVerificationLoading,
    signatureVerificationError,
    verifyDocumentSignature,
    clearSignatureVerification,
  } = useDocumentsStore();

  const [showSignatureModal, setShowSignatureModal] = useState(false);
  const [showVerifyModal, setShowVerifyModal] = useState(false);
  const [pdfCacheBuster, setPdfCacheBuster] = useState<number | null>(null);
  const [retrying, setRetrying] = useState(false);
  const [showDetails, setShowDetails] = useState(false);
  // Solo quien puede firmar digitalmente (admin) ve el detalle del error y el botón Reintentar
  const canSignDigital = useCan('documents.sign_digital');

  const currentDocId = currentDocument?.id;
  const digitalStatus = currentDocument?.digitalSignatureStatus ?? null;

  const digitalSignature = currentDocument?.digitalSignature ?? null;

  const pdfUrl = useMemo(() => {
    if (!documentId) return null;
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
    const url = `${baseUrl}/documents/${documentId}/preview`;
    return pdfCacheBuster ? `${url}?t=${pdfCacheBuster}` : url;
  }, [documentId, pdfCacheBuster]);

  useEffect(() => {
    if (documentId) {
      fetchDocumentById(parseInt(documentId));
    }
  }, [documentId, fetchDocumentById]);

  // Check if user has accepted signature terms
  useEffect(() => {
    checkSignatureTerms();
  }, [checkSignatureTerms]);

  // La firma PAdES de la empresa se (re)genera en segundo plano (p. ej. para incluir la
  // conformidad del trabajador): se consulta el estado liviano cada 3 s hasta 120 s.
  useEffect(() => {
    if (!currentDocId || digitalStatus !== 'pending') return;

    const controller = new AbortController();
    pollDigitalSignature(currentDocId, { intervalMs: 3000, timeoutMs: 120000, signal: controller.signal })
      .then((result) => {
        if (result === 'signed') {
          // El archivo servido cambió: forzar la recarga del visor
          setPdfCacheBuster(Date.now());
          // El mensaje depende del documento recargado: no afirmar una conformidad inexistente
          const doc = useDocumentsStore.getState().currentDocument;
          if (doc?.status === 'signed' && doc.digitalSignature?.includes_conformity) {
            toast.success("El PDF firmado por la empresa se actualizó con tu conformidad.");
          } else {
            toast.success("La firma digital de la empresa se aplicó.");
          }
        } else if (result === 'failed') {
          setPdfCacheBuster(Date.now());
          toast.error("No se pudo actualizar el PDF firmado.");
        } else if (result === 'timeout') {
          toast.info("Sigue en proceso; recarga más tarde.");
        }
      });

    return () => controller.abort();
  }, [currentDocId, digitalStatus, pollDigitalSignature]);

  const handleRetryDigital = async () => {
    if (!currentDocId) return;
    setRetrying(true);
    try {
      await retryDigitalSignature(currentDocId);
      toast.success("Reintentando la firma digital...");
    } catch (e) {
      toast.error(getErrorMessage(e));
    } finally {
      setRetrying(false);
    }
  };

  const handleSign = () => {
    setShowSignatureModal(true);
  };

  const handleSignatureSuccess = async () => {
    toast.success("¡Conformidad registrada exitosamente!");
    // Refresh document to get updated status
    if (documentId) {
      await fetchDocumentById(parseInt(documentId));
      // Force PDF viewer to reload by adding cache-busting timestamp
      setPdfCacheBuster(Date.now());
    }
  };

  const handleVerifySignature = () => {
    if (!documentId) return;
    setShowVerifyModal(true);
    verifyDocumentSignature(parseInt(documentId));
  };

  const handleCloseVerifyModal = () => {
    setShowVerifyModal(false);
    clearSignatureVerification();
  };

  const handleDownload = () => {
    if (documentId) {
      const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';
      window.open(`${baseUrl}/documents/${documentId}/download`, '_blank');
      toast.success("Descargando documento...");
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#2563EB] mx-auto mb-4" />
          <p className="text-[#64748B]">Cargando documento...</p>
        </div>
      </div>
    );
  }

  if (!currentDocument) {
    // Show error only when document is null (not loaded)
    if (error) {
      toast.error(error);
    }
    return (
      <div className="flex items-center justify-center h-96">
        <Card className="max-w-md">
          <CardContent className="p-6 text-center">
            <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
            <h3 className="mb-2">Documento no encontrado</h3>
            <p className="text-[#64748B] mb-4">{error || "No se pudo cargar el documento"}</p>
            <Button onClick={handleBack}>Volver</Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const isAdminView = !!currentDocument.user;
  const docUserId = currentDocument.userId;
  const currentUserId = user?.id ? Number(user.id) : null;
  const isOwner = docUserId !== null && currentUserId !== null && docUserId === currentUserId;
  const isPendingConformity = currentDocument.status === 'pending' && currentDocument.requiresSignature;
  const conformityTitle = isOwner ? "Tu conformidad" : "Conformidad del trabajador";

  const signerRows = digitalSignature ? formatSignerDetails(digitalSignature.signer_details) : [];
  const rowValue = (label: string) => signerRows.find((r) => r.label === label)?.value;
  const summaryRows = ["Razón social", "RUC", "Representante"]
    .map((label) => ({ label, value: rowValue(label) }))
    .filter((r): r is { label: string; value: string } => !!r.value);
  const detailRows = signerRows.filter((r) => ["Cargo", "País/Provincia"].includes(r.label));
  const hasSignerRows = signerRows.length > 0;

  const retryButton = canSignDigital && (
    <Button size="sm" variant="outline" className="gap-2 h-8" disabled={retrying} onClick={handleRetryDigital}>
      {retrying ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <RefreshCw className="w-3.5 h-3.5" />}
      Reintentar
    </Button>
  );

  return (
    <div className="space-y-3">
      {/* Barra superior compacta */}
      <div className="flex items-center justify-between gap-3 h-10">
        <div className="flex items-center gap-2 min-w-0">
          <Button variant="ghost" size="icon" onClick={handleBack} className="h-9 w-9 flex-shrink-0" aria-label="Volver">
            <ArrowLeft className="w-5 h-5" />
          </Button>
          <h1 className="text-lg font-semibold truncate">Visor de documento</h1>
        </div>
        <Button variant="outline" className="gap-2 h-9" onClick={handleDownload}>
          <Download className="w-4 h-4" />
          <span>Descargar</span>
        </Button>
      </div>

      {/* 100vh - navbar (80) - padding de main (48) - barra superior (40) - separación (12) */}
      <div className="grid grid-cols-1 lg:grid-cols-3 lg:grid-rows-[minmax(0,1fr)] gap-4 lg:h-[calc(100vh-180px)]">
        {/* Visor PDF: toda la altura disponible en desktop */}
        <div className="lg:col-span-2 lg:h-full lg:min-h-0 min-w-0">
          <Card className="overflow-hidden h-full py-0 gap-0" data-testid="pdf-card">
            <CardContent className="p-0 h-full">
              <div className="w-full h-[70vh] min-h-[320px] lg:h-full">
                {pdfUrl ? (
                  <PDFViewer url={pdfUrl} />
                ) : (
                  <div className="flex items-center justify-center h-full bg-gray-100">
                    <div className="text-center">
                      <FileText className="w-16 h-16 text-[#64748B] mx-auto mb-4" />
                      <p className="text-[#64748B]">No se puede mostrar el documento</p>
                    </div>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Panel derecho con scroll propio */}
        <div className="space-y-3 min-w-0 lg:h-full lg:overflow-y-auto lg:pr-1" data-testid="side-panel">
          {/* a) Información del documento compacta */}
          <Card className="py-0 gap-0" data-testid="document-info">
            <CardContent className="p-4">
              <div className="flex items-start justify-between gap-2 mb-3">
                <h2 className="font-semibold leading-tight min-w-0">{currentDocument.documentType?.displayName || "Documento"}</h2>
                {getDocumentStatusBadge(currentDocument.status)}
              </div>
              <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                  <dt className="text-xs text-[#64748B]">Período</dt>
                  <dd>{currentDocument.period}</dd>
                </div>
                <div>
                  <dt className="text-xs text-[#64748B]">Fecha subida</dt>
                  <dd>{formatDate(currentDocument.createdAt)}</dd>
                </div>
                {isAdminView && currentDocument.user && (
                  <div className="col-span-2">
                    <dt className="text-xs text-[#64748B]">Usuario</dt>
                    <dd>{currentDocument.user.name} {currentDocument.user.lastName}</dd>
                  </div>
                )}
              </dl>
            </CardContent>
          </Card>

          {/* b) Conformidad del trabajador (código por correo): no es una firma criptográfica */}
          {isPendingConformity && (isOwner || digitalSignature) && (
            <Card className="border-[#2563EB] py-0 gap-0" data-testid="conformity-card">
              <CardContent className="p-4 space-y-3">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <FileText className="w-4 h-4 text-[#2563EB]" />
                  </div>
                  <div>
                    <h3 className="text-[#1E40AF] font-semibold leading-tight">{conformityTitle}</h3>
                    <p className="text-sm text-[#64748B]">Pendiente</p>
                  </div>
                </div>
                {isOwner && (
                  <>
                    <p className="text-xs text-[#64748B]">
                      Lee el documento completo antes de firmar. Tu firma tendrá validez legal.
                    </p>
                    <Button className="w-full h-11 bg-[#2563EB] hover:bg-[#1E40AF]" onClick={handleSign}>
                      <CheckCircle className="w-5 h-5 mr-2" />
                      Firmar documento
                    </Button>
                  </>
                )}
              </CardContent>
            </Card>
          )}

          {!(isPendingConformity && (isOwner || digitalSignature)) && currentDocument.status === 'signed' && (
            <Card className="border-green-500 py-0 gap-0" data-testid="conformity-card">
              <CardContent className="p-4">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <CheckCircle className="w-4 h-4 text-green-600" />
                  </div>
                  <div>
                    <h3 className="text-green-700 font-semibold leading-tight">{conformityTitle}</h3>
                    <p className="text-sm text-[#64748B]">
                      {currentDocument.signedAt
                        ? `Firmada el ${formatDateTime(currentDocument.signedAt)}`
                        : "Este documento ya ha sido firmado"}
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* c) Firma criptográfica PAdES de la empresa, independiente de la conformidad */}
          {digitalSignature && (
            <Card className="border-green-500 py-0 gap-0" data-testid="company-signature-card">
              <CardContent className="p-4 space-y-3">
                <div className="flex items-center gap-2 flex-wrap">
                  <ShieldCheck className="w-5 h-5 text-green-600 flex-shrink-0" />
                  <h3 className="text-green-700 font-semibold whitespace-nowrap">Firmado por la empresa</h3>
                  <Badge variant="outline" className="text-[10px] px-1.5 py-0 border-green-300 text-green-700">
                    {digitalSignature.certificate_source === "tenant"
                      ? "Certificado de la empresa"
                      : "Certificado de plataforma"}
                  </Badge>
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <button
                        type="button"
                        aria-label="Sobre la confiabilidad de la firma"
                        className="ml-auto text-[#64748B] hover:text-[#2563EB] focus-visible:outline focus-visible:outline-2 rounded-full"
                      >
                        <Info className="w-4 h-4" />
                      </button>
                    </TooltipTrigger>
                    <TooltipContent side="left" className="max-w-xs text-xs">
                      Que la firma sea "confiable" depende de que el certificado esté emitido por una
                      entidad certificadora acreditada; con un certificado de prueba la verificación
                      normalmente mostrará "No confiable" aunque la firma sea íntegra y válida.
                    </TooltipContent>
                  </Tooltip>
                </div>

                {summaryRows.length > 0 && (
                  <div className="space-y-1.5 text-sm">
                    {summaryRows.map((row) => (
                      <div key={row.label} className="flex justify-between gap-2">
                        <span className="text-[#64748B] whitespace-nowrap">{row.label}</span>
                        <span className="font-medium text-right break-words min-w-0">{row.value}</span>
                      </div>
                    ))}
                  </div>
                )}
                {!hasSignerRows && digitalSignature.certificate_ruc && (
                  <div className="flex justify-between gap-2 text-sm">
                    <span className="text-[#64748B]">RUC del certificado</span>
                    <span className="font-medium">{digitalSignature.certificate_ruc}</span>
                  </div>
                )}

                {digitalSignature.includes_conformity && (
                  <Badge
                    data-testid="includes-conformity-badge"
                    variant="outline"
                    className="gap-1 border-green-300 bg-green-50 text-green-700"
                  >
                    <CheckCircle className="w-3 h-3" />
                    Incluye tu conformidad
                  </Badge>
                )}

                <Collapsible open={showDetails} onOpenChange={setShowDetails}>
                  <CollapsibleTrigger asChild>
                    <button
                      type="button"
                      aria-expanded={showDetails}
                      className="flex items-center gap-1 text-sm text-[#2563EB] hover:underline"
                    >
                      {showDetails ? "Ocultar detalles" : "Ver detalles"}
                      <ChevronDown className={`w-4 h-4 transition-transform ${showDetails ? "rotate-180" : ""}`} />
                    </button>
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <div className="space-y-1.5 text-sm mt-2" data-testid="signature-details">
                      {!hasSignerRows && (
                        <div className="flex justify-between gap-2">
                          <span className="text-[#64748B]">Firmante</span>
                          <span className="font-medium text-right break-words min-w-0">
                            {digitalSignature.signer_subject || "-"}
                          </span>
                        </div>
                      )}
                      {detailRows.map((row) => (
                        <div key={row.label} className="flex justify-between gap-2">
                          <span className="text-[#64748B]">{row.label}</span>
                          <span className="font-medium text-right">{row.value}</span>
                        </div>
                      ))}
                      <div className="flex justify-between gap-2">
                        <span className="text-[#64748B]">Fecha de firma</span>
                        <span className="font-medium text-right">
                          {digitalSignature.signing_time ? formatDateTime(digitalSignature.signing_time) : "-"}
                        </span>
                      </div>
                      <div className="flex justify-between gap-2 items-center">
                        <span className="text-[#64748B] flex items-center gap-1">
                          <Clock className="w-3.5 h-3.5" /> Sello de tiempo (TSA)
                        </span>
                        <span className="font-medium">{digitalSignature.tsa_applied ? "Sí" : "No"}</span>
                      </div>
                    </div>
                  </CollapsibleContent>
                </Collapsible>

                {digitalStatus === 'pending' && (
                  <div
                    className="flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-gray-700"
                    data-testid="digital-pending-alert"
                  >
                    <Loader2 className="w-4 h-4 animate-spin text-amber-600" />
                    Actualizando el PDF firmado…
                  </div>
                )}

                {digitalStatus === 'failed' && (
                  <Alert variant="destructive" className="py-2" data-testid="digital-failed-alert">
                    <AlertCircle className="w-4 h-4" />
                    <AlertDescription className="text-xs space-y-2">
                      <p>El PDF firmado aún no incluye la conformidad.</p>
                      {canSignDigital && (
                        <>
                          {currentDocument.digitalSignatureError && (
                            <p className="break-words">{currentDocument.digitalSignatureError}</p>
                          )}
                          {retryButton}
                        </>
                      )}
                    </AlertDescription>
                  </Alert>
                )}

                <Button variant="outline" className="w-full gap-2 h-9" onClick={handleVerifySignature}>
                  <ShieldCheck className="w-4 h-4" />
                  Verificar firma
                </Button>
              </CardContent>
            </Card>
          )}

          {/* d) Firma de la empresa en proceso o fallida por primera vez (sin metadata PAdES) */}
          {!digitalSignature && digitalStatus === 'pending' && (
            <Card className="border-amber-300 py-0 gap-0">
              <CardContent className="p-4 flex items-center gap-3" data-testid="digital-pending-alert">
                <Loader2 className="w-5 h-5 animate-spin text-amber-600" />
                <div>
                  <h3 className="text-amber-700 font-semibold leading-tight">Firma de la empresa</h3>
                  <p className="text-sm text-[#64748B]">Aplicando la firma digital…</p>
                </div>
              </CardContent>
            </Card>
          )}

          {!digitalSignature && digitalStatus === 'failed' && (
            <Card className="border-red-300 py-0 gap-0">
              <CardContent className="p-4 space-y-2" data-testid="digital-failed-alert">
                <div className="flex items-center gap-3">
                  <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0" />
                  <div>
                    <h3 className="text-red-700 font-semibold leading-tight">Firma de la empresa: error</h3>
                    <p className="text-sm text-[#64748B]">No se pudo aplicar la firma digital.</p>
                  </div>
                </div>
                {canSignDigital && (
                  <>
                    {currentDocument.digitalSignatureError && (
                      <p className="text-xs break-words text-gray-700">{currentDocument.digitalSignatureError}</p>
                    )}
                    {retryButton}
                  </>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      {/* Verify Signature Modal */}
      <VerifySignatureModal
        isOpen={showVerifyModal}
        onClose={handleCloseVerifyModal}
        isLoading={signatureVerificationLoading}
        error={signatureVerificationError}
        result={signatureVerification}
        signerDetails={digitalSignature?.signer_details ?? null}
        storedSignerSubject={digitalSignature?.signer_subject ?? null}
      />

      {/* Signature Modal */}
      {currentDocument && (
        <DocumentSignatureModal
          isOpen={showSignatureModal}
          onClose={() => setShowSignatureModal(false)}
          onSuccess={handleSignatureSuccess}
          documentId={parseInt(documentId || "0")}
          documentType={currentDocument.documentType?.displayName || "Documento"}
          period={currentDocument.period}
          requiresTermsAcceptance={!signatureTermsAccepted}
          onRequestCode={requestSignatureCode}
          onVerifyCode={signDocument}
          onAcceptTerms={acceptSignatureTerms}
        />
      )}
    </div>
  );
}

export default DocumentViewerView;
