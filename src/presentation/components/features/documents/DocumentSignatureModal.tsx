import { useState, useEffect } from "react";
import { Loader2, Mail, Shield, CheckCircle, AlertCircle, Info } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/presentation/components/ui/dialog";
import { Button } from "@/presentation/components/ui/button";
import { Alert, AlertDescription } from "@/presentation/components/ui/alert";
import { Checkbox } from "@/presentation/components/ui/checkbox";
import { OTPInput } from "@/presentation/components/ui/otp-input";

interface DocumentSignatureModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  documentId: number;
  documentType: string;
  period: string;
  requiresTermsAcceptance: boolean;
  onRequestCode: (documentId: number) => Promise<{ expiresIn: number; emailSentTo: string }>;
  onVerifyCode: (documentId: number, code: string) => Promise<void>;
  onAcceptTerms: () => Promise<void>;
}

type ModalStep = "terms" | "request-code" | "verify-code" | "success" | "error";

export function DocumentSignatureModal({
  isOpen,
  onClose,
  onSuccess,
  documentId,
  documentType,
  period,
  requiresTermsAcceptance,
  onRequestCode,
  onVerifyCode,
  onAcceptTerms,
}: DocumentSignatureModalProps) {
  const [step, setStep] = useState<ModalStep>(requiresTermsAcceptance ? "terms" : "request-code");
  const [code, setCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [emailSentTo, setEmailSentTo] = useState("");
  const [remainingTime, setRemainingTime] = useState(0);
  const [attempts, setAttempts] = useState(0);
  const MAX_ATTEMPTS = 3;

  // Reset state when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      console.log('[DocumentSignatureModal] Modal opened');
      console.log('[DocumentSignatureModal] requiresTermsAcceptance:', requiresTermsAcceptance);
      const initialStep = requiresTermsAcceptance ? "terms" : "request-code";
      console.log('[DocumentSignatureModal] Setting initial step to:', initialStep);
      setStep(initialStep);
      setCode("");
      setError(null);
      setTermsAccepted(false);
      setEmailSentTo("");
      setAttempts(0);
    }
  }, [isOpen, requiresTermsAcceptance]);

  // Countdown timer for code expiration
  useEffect(() => {
    if (step === "verify-code" && remainingTime > 0) {
      const timer = setInterval(() => {
        setRemainingTime((prev) => Math.max(0, prev - 1));
      }, 1000);
      return () => clearInterval(timer);
    }
  }, [step, remainingTime]);

  const handleAcceptTerms = async () => {
    if (!termsAccepted) return;

    console.log('[DocumentSignatureModal] Accepting terms...');
    setLoading(true);
    setError(null);

    try {
      await onAcceptTerms();
      console.log('[DocumentSignatureModal] Terms accepted successfully');
      setStep("request-code");
    } catch (err: any) {
      console.error('[DocumentSignatureModal] Error accepting terms:', err);
      setError(err.response?.data?.error || "Error al aceptar términos");
    } finally {
      setLoading(false);
    }
  };

  const handleRequestCode = async () => {
    console.log('[DocumentSignatureModal] Requesting signature code for document:', documentId);
    setLoading(true);
    setError(null);

    try {
      const result = await onRequestCode(documentId);
      console.log('[DocumentSignatureModal] Code request successful:', result);
      setEmailSentTo(result.emailSentTo);
      setRemainingTime(result.expiresIn);
      setAttempts(0); // Reset attempts for new code
      setStep("verify-code");
    } catch (err: any) {
      console.error('[DocumentSignatureModal] Error requesting code:', err);
      const errorData = err.response?.data;
      console.log('[DocumentSignatureModal] Error data:', errorData);

      // If user hasn't accepted terms, redirect to terms step
      if (errorData?.requires_terms === true) {
        console.log('[DocumentSignatureModal] User has not accepted terms, redirecting to terms step');
        setError(null); // Clear error, just redirect
        setStep("terms");
        return;
      }

      // Handle cooldown error with remaining time
      if (err.response?.status === 429 && errorData?.cooldown_remaining) {
        const seconds = errorData.cooldown_remaining;
        setError(`Debes esperar ${seconds} segundos antes de solicitar otro código.`);
        console.log('[DocumentSignatureModal] Cooldown error, staying in request-code step');
        setStep("request-code");
      } else {
        const errorMsg = errorData?.error || "Error al solicitar código";
        setError(errorMsg);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyCode = async () => {
    if (code.length !== 6) {
      setError("El código debe tener 6 dígitos");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await onVerifyCode(documentId, code);
      setStep("success");
      setTimeout(() => {
        onSuccess();
        onClose();
      }, 2000);
    } catch (err: any) {
      const errorData = err.response?.data;
      const newAttempts = attempts + 1;
      setAttempts(newAttempts);

      // Check if max attempts reached
      if (newAttempts >= MAX_ATTEMPTS) {
        setError(`Máximo de intentos alcanzado (${MAX_ATTEMPTS}). Solicita un nuevo código.`);
        setTimeout(() => {
          setStep("request-code");
          setCode("");
          setAttempts(0);
        }, 2000);
      } else {
        const remainingAttempts = MAX_ATTEMPTS - newAttempts;
        setError(`Código incorrecto. Te quedan ${remainingAttempts} ${remainingAttempts === 1 ? 'intento' : 'intentos'}.`);
        setCode(""); // Clear code for retry
      }

      // If backend says need new code, go back to request step
      if (errorData?.requires_new_code) {
        setStep("request-code");
        setCode("");
        setAttempts(0);
      }
    } finally {
      setLoading(false);
    }
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  };

  const handleClose = () => {
    if (loading) return;
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        {/* STEP 1: Terms & Conditions */}
        {step === "terms" && (
          <>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <Shield className="w-4 h-4 text-[#2563EB]" />
                Términos de Firma Digital
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-4">
              <div className="bg-gray-50 p-3 rounded-md max-h-40 overflow-y-auto text-xs">
                <p className="mb-2 font-medium text-gray-700">
                  Al aceptar estos términos, confirmas que:
                </p>
                <ul className="list-disc list-inside space-y-1 text-gray-600">
                  <li>Tu firma digital tendrá validez legal</li>
                  <li>Eres responsable de tu cuenta</li>
                  <li>Has leído el documento a firmar</li>
                  <li>Autorizas verificación 2FA vía email</li>
                  <li>La firma será registrada (IP, fecha, hora)</li>
                </ul>
              </div>

              <div className="flex items-start gap-2">
                <Checkbox
                  id="terms-checkbox"
                  checked={termsAccepted}
                  onCheckedChange={(checked: boolean | "indeterminate") => setTermsAccepted(checked === true)}
                />
                <label htmlFor="terms-checkbox" className="text-sm cursor-pointer leading-tight">
                  He leído y acepto los términos de firma digital
                </label>
              </div>

              {error && (
                <Alert variant="destructive" className="py-2">
                  <AlertCircle className="w-4 h-4" />
                  <AlertDescription className="text-xs">{error}</AlertDescription>
                </Alert>
              )}

              <div className="flex gap-2 pt-2">
                <Button variant="outline" onClick={handleClose} className="flex-1" size="sm">
                  Cancelar
                </Button>
                <Button
                  onClick={handleAcceptTerms}
                  disabled={!termsAccepted || loading}
                  className="flex-1 bg-[#2563EB] hover:bg-[#1E40AF]"
                  size="sm"
                >
                  {loading ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Procesando...
                    </>
                  ) : (
                    "Aceptar y Continuar"
                  )}
                </Button>
              </div>
            </div>
          </>
        )}

        {/* STEP 2: Request Code */}
        {step === "request-code" && (
          <>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <Mail className="w-4 h-4 text-[#2563EB]" />
                Verificación en Dos Pasos
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-4">
              <div className="bg-blue-50 p-3 rounded-md border border-blue-100">
                <p className="text-xs text-gray-600 mb-1">Documento a firmar:</p>
                <p className="text-sm font-medium text-gray-900">
                  {documentType} - {period}
                </p>
              </div>

              <Alert className="py-2 border-blue-200 bg-blue-50">
                <Info className="w-4 h-4 text-blue-600" />
                <AlertDescription className="text-xs text-gray-700">
                  Enviaremos un código de 6 dígitos a tu correo. Válido por 10 minutos.
                </AlertDescription>
              </Alert>

              {error && (
                <Alert variant="destructive" className="py-2">
                  <AlertCircle className="w-4 h-4" />
                  <AlertDescription className="text-xs">{error}</AlertDescription>
                </Alert>
              )}

              <div className="flex gap-2 pt-2">
                <Button variant="outline" onClick={handleClose} className="flex-1" size="sm">
                  Cancelar
                </Button>
                <Button
                  onClick={handleRequestCode}
                  disabled={loading}
                  className="flex-1 bg-[#2563EB] hover:bg-[#1E40AF]"
                  size="sm"
                >
                  {loading ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Enviando...
                    </>
                  ) : (
                    <>
                      <Mail className="w-4 h-4 mr-2" />
                      Enviar Código
                    </>
                  )}
                </Button>
              </div>
            </div>
          </>
        )}

        {/* STEP 3: Verify Code */}
        {step === "verify-code" && (
          <>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-base">
                <Shield className="w-4 h-4 text-[#2563EB]" />
                Ingresa el Código
              </DialogTitle>
            </DialogHeader>

            <div className="space-y-4">
              <div className="text-center">
                <p className="text-xs text-gray-600 mb-3">Código enviado a <span className="font-medium">{emailSentTo}</span></p>
                <OTPInput
                  value={code}
                  onChange={setCode}
                  length={6}
                  autoFocus
                  disabled={loading}
                />
                <div className="flex items-center justify-center gap-1.5 mt-2 text-xs text-gray-600">
                  <Shield className="w-3 h-3" />
                  <span>Expira en: <span className="font-mono font-semibold text-[#2563EB]">{formatTime(remainingTime)}</span></span>
                </div>
              </div>

              {error && (
                <Alert variant="destructive" className="py-2">
                  <AlertCircle className="w-4 h-4" />
                  <AlertDescription className="text-xs">{error}</AlertDescription>
                </Alert>
              )}

              <div className="flex gap-2 pt-2">
                <Button
                  variant="outline"
                  onClick={() => setStep("request-code")}
                  className="flex-1"
                  disabled={loading}
                  size="sm"
                >
                  Nuevo Código
                </Button>
                <Button
                  onClick={handleVerifyCode}
                  disabled={code.length !== 6 || loading}
                  className="flex-1 bg-[#2563EB] hover:bg-[#1E40AF]"
                  size="sm"
                >
                  {loading ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Verificando...
                    </>
                  ) : (
                    "Verificar y Firmar"
                  )}
                </Button>
              </div>
            </div>
          </>
        )}

        {/* STEP 4: Success */}
        {step === "success" && (
          <>
            <div className="py-8 text-center">
              <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <CheckCircle className="w-8 h-8 text-green-600" />
              </div>
              <h3 className="text-lg font-semibold text-green-600 mb-2">¡Firma Exitosa!</h3>
              <p className="text-sm text-gray-600">
                Tu documento ha sido firmado correctamente
              </p>
            </div>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}
