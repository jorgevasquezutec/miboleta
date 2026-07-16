// Domain Entity - Configuración general de la plataforma (aligned with
// backend PlatformSettingsController::transform()). Guarda:
//  - la IP pública del servidor (ítem 23 del sprint-fix), registrada
//    manualmente por root con fines informativos (whitelisting con terceros), y
//  - el servidor SMTP GLOBAL de la plataforma (mailer por defecto), usado
//    cuando una empresa no tiene SMTP propio. Precedencia:
//    SMTP de la empresa -> SMTP global -> .env del servidor.
// Solo root puede leer/editar esto (GET/PUT /platform/settings).
import type { MailEncryption } from './Tenant';

export interface PlatformSettings {
  publicIp: string | null;
  // SMTP global (la contraseña nunca llega al frontend: solo el booleano).
  mailHost: string | null;
  mailPort: number | null;
  mailUsername: string | null;
  mailEncryption: MailEncryption | null;
  mailFromAddress: string | null;
  mailFromName: string | null;
  hasMailPassword: boolean;
  updatedAt: string | null;
}
