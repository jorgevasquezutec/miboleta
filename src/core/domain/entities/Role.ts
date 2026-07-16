// Domain Entity - Role
// Catálogo de roles del sistema (incluye 'root', que es global). Al construir
// selectores de rol POR EMPRESA (tenants_config.*.role_ids en UserFormPage),
// filtrar 'root' del catálogo: no se asigna dentro de una empresa.
export interface Role {
  id: number;
  name: string;
  display_name: string;
}
