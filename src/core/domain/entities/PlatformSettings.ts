// Domain Entity - Configuración general de la plataforma (aligned with
// backend PlatformSettingsController::transform()). Por ahora solo guarda
// la IP pública del servidor (ítem 23 del sprint-fix), registrada
// manualmente por root con fines informativos (whitelisting con terceros).
// Solo root puede leer/editar esto (GET/PUT /platform/settings).
export interface PlatformSettings {
  publicIp: string | null;
  updatedAt: string | null;
}
