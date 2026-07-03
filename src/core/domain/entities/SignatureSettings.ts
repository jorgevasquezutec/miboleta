// Domain Entity - Configuración del certificado de firma digital ÚNICO de la
// plataforma (aligned with backend SignatureSettingsController::transform()).
// Solo root puede leer/editar esto (GET/PUT /signature/settings,
// POST/DELETE /signature/certificate). Nunca expone password ni el binario.
export interface SignatureSettings {
  signatureEnabled: boolean;
  hasCertificate: boolean;
  certificateSubject: string | null;
  tsaUrl: string | null;
  uploadedAt: string | null;
}
