import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/presentation/components/ui/dialog";
import { Button } from "@/presentation/components/ui/button";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { Separator } from "@/presentation/components/ui/separator";
import {
  Loader2,
  ShieldCheck,
  ShieldAlert,
  CheckCircle2,
  XCircle,
  Info,
  Clock,
} from "lucide-react";
import { VerifySignatureResponse } from "@/core/domain/repositories/IDocumentRepository";
import { formatDateTime } from "@/presentation/utils";

interface VerifySignatureModalProps {
  isOpen: boolean;
  onClose: () => void;
  isLoading: boolean;
  error: string | null;
  result: VerifySignatureResponse | null;
}

function ResultRow({
  label,
  ok,
  hint,
}: {
  label: string;
  ok: boolean | null | undefined;
  hint?: string;
}) {
  return (
    <div className="flex items-start justify-between gap-4 py-2">
      <div>
        <p className="text-sm font-medium text-gray-900">{label}</p>
        {hint && <p className="text-xs text-[#64748B] mt-0.5">{hint}</p>}
      </div>
      {ok === null || ok === undefined ? (
        <span className="text-xs text-[#64748B]">No disponible</span>
      ) : ok ? (
        <span className="flex items-center gap-1 text-green-600 text-sm font-medium flex-shrink-0">
          <CheckCircle2 className="w-4 h-4" /> Sí
        </span>
      ) : (
        <span className="flex items-center gap-1 text-red-600 text-sm font-medium flex-shrink-0">
          <XCircle className="w-4 h-4" /> No
        </span>
      )}
    </div>
  );
}

export function VerifySignatureModal({ isOpen, onClose, isLoading, error, result }: VerifySignatureModalProps) {
  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ShieldCheck className="w-5 h-5 text-[#2563EB]" />
            Verificación de Firma Digital
          </DialogTitle>
        </DialogHeader>

        {isLoading && (
          <div className="py-8 text-center">
            <Loader2 className="w-10 h-10 animate-spin text-[#2563EB] mx-auto mb-3" />
            <p className="text-sm text-[#64748B]">Verificando firma con el servicio de firma digital...</p>
          </div>
        )}

        {!isLoading && error && (
          <Alert variant="destructive">
            <ShieldAlert className="w-4 h-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {!isLoading && !error && result && !result.verifiable && (
          <Alert className="border-amber-200 bg-amber-50">
            <Info className="w-4 h-4 text-amber-600" />
            <AlertDescription className="text-sm text-gray-700">
              {result.message || "Este documento no tiene una firma digital criptográfica verificable."}
            </AlertDescription>
          </Alert>
        )}

        {!isLoading && !error && result && result.verifiable && (
          <div className="space-y-1">
            <ResultRow
              label="Integridad del archivo"
              ok={result.intact}
              hint="El PDF no fue modificado después de firmarse"
            />
            <Separator />
            <ResultRow
              label="Firma válida"
              ok={result.valid}
              hint="La firma criptográfica corresponde al contenido del documento"
            />
            <Separator />
            <ResultRow
              label="Certificado confiable"
              ok={result.trusted}
              hint="Depende de que el certificado esté emitido por una entidad certificadora acreditada. Con un certificado de prueba, este valor normalmente será 'No'."
            />
            <Separator />
            <ResultRow
              label="Cubre todo el archivo"
              ok={result.coversWholeFile}
              hint="La firma abarca el documento completo, sin contenido agregado después"
            />
            <Separator />

            <div className="pt-3 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-[#64748B]">Firmante</span>
                <span className="font-medium text-right">{result.signerSubject || "-"}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-[#64748B]">Fecha de firma</span>
                <span className="font-medium">{result.signingTime ? formatDateTime(result.signingTime) : "-"}</span>
              </div>
              <div className="flex justify-between text-sm items-center">
                <span className="text-[#64748B] flex items-center gap-1">
                  <Clock className="w-3.5 h-3.5" /> Sello de tiempo (TSA)
                </span>
                <span className="font-medium">
                  {result.tsaApplied ? (result.tsaTime ? formatDateTime(result.tsaTime) : "Aplicado") : "No aplicado"}
                </span>
              </div>
            </div>
          </div>
        )}

        <div className="pt-2">
          <Button variant="outline" onClick={onClose} className="w-full">
            Cerrar
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default VerifySignatureModal;
