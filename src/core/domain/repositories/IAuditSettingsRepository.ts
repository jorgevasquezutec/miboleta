import { AuditActionSetting } from '../entities/AuditSettings';

export interface IAuditSettingsRepository {
  getCatalog(): Promise<AuditActionSetting[]>;
  // Envía el conjunto de acciones cuya captura se DESACTIVA; devuelve el
  // catálogo actualizado.
  updateSettings(disabledActions: string[]): Promise<AuditActionSetting[]>;
}
