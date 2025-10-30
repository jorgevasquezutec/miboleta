import { useState } from "react";
import { ArrowLeft, Download, Share2, FileText, CheckCircle, Info } from "lucide-react";
import { Button } from "../ui/button";
import { Card, CardContent } from "../ui/card";
import { Checkbox } from "../ui/checkbox";
import { Badge } from "../ui/badge";
import { Separator } from "../ui/separator";
import { Alert, AlertDescription } from "../ui/alert";

interface DocumentViewerViewProps {
  onBack: () => void;
}

export function DocumentViewerView({ onBack }: DocumentViewerViewProps) {
  const [hasRead, setHasRead] = useState(false);
  const [isSigning, setIsSigning] = useState(false);

  const handleSign = () => {
    setIsSigning(true);
    // Simulate signing process
    setTimeout(() => {
      setIsSigning(false);
      alert("¡Documento firmado exitosamente!");
      onBack();
    }, 1500);
  };

  return (
    <div className="min-h-screen bg-[#F1F5F9]">
      {/* Header */}
      <div className="bg-white border-b border-[rgba(0,0,0,0.1)] px-6 py-4">
        <div className="max-w-[1400px] mx-auto flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" onClick={onBack}>
              <ArrowLeft className="w-5 h-5" />
            </Button>
            <div>
              <div className="flex items-center gap-3">
                <h2>Contrato de Trabajo - 2025</h2>
                <Badge className="bg-[#F59E0B] text-white">Pendiente Firma</Badge>
              </div>
              <p className="text-[#64748B]">Enviado el 10 Jun 2025</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" className="gap-2">
              <Download className="w-4 h-4" />
              Descargar
            </Button>
            <Button variant="outline" className="gap-2">
              <Share2 className="w-4 h-4" />
              Compartir
            </Button>
          </div>
        </div>
      </div>

      <div className="max-w-[1400px] mx-auto p-6">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Document Viewer */}
          <div className="lg:col-span-2">
            <Card>
              <CardContent className="p-0">
                {/* PDF Viewer Placeholder */}
                <div className="bg-white aspect-[8.5/11] flex items-center justify-center border-b border-[rgba(0,0,0,0.1)]">
                  <div className="text-center p-12">
                    <FileText className="w-24 h-24 text-[#64748B] mx-auto mb-4" />
                    <h3 className="text-[#1E40AF] mb-2">Vista Previa del Documento</h3>
                    <p className="text-[#64748B]">
                      Aquí se mostraría el contenido del PDF
                    </p>
                    <div className="mt-8 space-y-4 max-w-2xl mx-auto text-left">
                      <div className="p-6 bg-[#F1F5F9] rounded-lg">
                        <h4 className="mb-4">CONTRATO DE TRABAJO</h4>
                        <p className="text-[#64748B] mb-4">
                          Entre la empresa MiBoleta S.A.C., en adelante "EL
                          EMPLEADOR", y el Sr./Sra. [NOMBRE DEL EMPLEADO], en adelante
                          "EL TRABAJADOR", se acuerda celebrar el presente Contrato de
                          Trabajo, sujeto a las siguientes cláusulas:
                        </p>
                        <h4 className="mb-2">PRIMERA: OBJETO DEL CONTRATO</h4>
                        <p className="text-[#64748B] mb-4">
                          EL EMPLEADOR contrata los servicios de EL TRABAJADOR para
                          desempeñar el cargo de [CARGO], en el área de [ÁREA].
                        </p>
                        <h4 className="mb-2">SEGUNDA: PLAZO</h4>
                        <p className="text-[#64748B] mb-4">
                          El presente contrato tiene una duración indeterminada,
                          iniciando el [FECHA DE INICIO].
                        </p>
                        <h4 className="mb-2">TERCERA: REMUNERACIÓN</h4>
                        <p className="text-[#64748B]">
                          EL EMPLEADOR se obliga a pagar mensualmente a EL TRABAJADOR
                          la suma de S/ [MONTO] (Soles).
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Document Controls */}
                <div className="p-4 bg-white flex items-center justify-center gap-4">
                  <Button variant="outline" size="sm">
                    Página Anterior
                  </Button>
                  <span className="text-[#64748B]">Página 1 de 3</span>
                  <Button variant="outline" size="sm">
                    Página Siguiente
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Signature Panel */}
          <div className="space-y-6">
            {/* Document Info */}
            <Card>
              <CardContent className="p-6 space-y-4">
                <div>
                  <h3 className="mb-4">Información del Documento</h3>
                  <div className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-[#64748B]">Tipo:</span>
                      <span>Contrato</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between">
                      <span className="text-[#64748B]">Fecha envío:</span>
                      <span>10 Jun 2025</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between">
                      <span className="text-[#64748B]">Vencimiento:</span>
                      <span className="text-[#F59E0B]">20 Jun 2025</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between">
                      <span className="text-[#64748B]">Páginas:</span>
                      <span>3</span>
                    </div>
                    <Separator />
                    <div className="flex justify-between">
                      <span className="text-[#64748B]">Tamaño:</span>
                      <span>245 KB</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Signature Section */}
            <Card className="border-[#2563EB]">
              <CardContent className="p-6 space-y-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <FileText className="w-5 h-5 text-[#2563EB]" />
                  </div>
                  <div>
                    <h3 className="text-[#1E40AF]">Firma Digital</h3>
                    <p className="text-[#64748B]">
                      Requerida para continuar
                    </p>
                  </div>
                </div>

                <Alert>
                  <Info className="w-4 h-4" />
                  <AlertDescription>
                    Por favor lee el documento completo antes de firmar. Tu firma
                    tendrá validez legal.
                  </AlertDescription>
                </Alert>

                <div className="space-y-4 pt-4">
                  <div className="flex items-start gap-3">
                    <Checkbox
                      id="read-confirm"
                      checked={hasRead}
                      onCheckedChange={(checked) => setHasRead(checked as boolean)}
                    />
                    <label htmlFor="read-confirm" className="cursor-pointer flex-1">
                      <p>
                        He leído y acepto los términos del presente documento.
                        Entiendo que esta firma digital tiene el mismo valor legal
                        que una firma manuscrita.
                      </p>
                    </label>
                  </div>

                  <Button
                    className="w-full h-12 bg-[#2563EB] hover:bg-[#1E40AF]"
                    disabled={!hasRead || isSigning}
                    onClick={handleSign}
                  >
                    {isSigning ? (
                      "Firmando..."
                    ) : (
                      <>
                        <CheckCircle className="w-5 h-5 mr-2" />
                        Firmar Documento
                      </>
                    )}
                  </Button>

                  <p className="text-center text-[#64748B]">
                    Al firmar, se generará un certificado digital con tu información
                    y timestamp.
                  </p>
                </div>
              </CardContent>
            </Card>

            {/* Signature Details */}
            <Card>
              <CardContent className="p-6">
                <h4 className="mb-3">Detalles de la Firma</h4>
                <div className="space-y-2 text-[#64748B]">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 mt-0.5 text-[#10B981]" />
                    <span>Firma digital certificada</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 mt-0.5 text-[#10B981]" />
                    <span>Timestamp automático</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 mt-0.5 text-[#10B981]" />
                    <span>Geolocalización incluida</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 mt-0.5 text-[#10B981]" />
                    <span>Certificado de validez</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  );
}
