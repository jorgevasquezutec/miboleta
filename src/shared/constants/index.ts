/**
 * Application Constants
 */

export const APP_NAME = 'MiBoleta';
// Fuente única de la versión que se muestra en pantalla (login, pie del menú
// lateral y menú de usuario). Debe coincidir con la de la documentación, que
// vive en docs/pdf-generator/version.js, y con el tag git de la entrega.
export const APP_VERSION = '1.0';
export const APP_DESCRIPTION = 'Sistema de gestión de documentos';

// Document Types
export const DOCUMENT_TYPES = {
  DNI: 'dni',
  RUC: 'ruc',
  CE: 'ce',
  PASSPORT: 'passport',
} as const;

export const DOCUMENT_TYPE_LABELS = {
  dni: 'DNI',
  ruc: 'RUC',
  ce: 'Carné de Extranjería',
  passport: 'Pasaporte',
} as const;

// User Roles
export const USER_ROLES = {
  ROOT: 'root',
  ADMIN: 'admin',
  CLIENT: 'client',
  APROBADOR: 'aprobador',
  ADMIN_TENANT: 'admin_tenant',
} as const;

// Roles de EMPRESA que cuentan como "empleado" (vs. cuentas de aplicación:
// root y admin_tenant). Espejo de backend User::ORG_EMPLOYEE_ROLES.
export const ORG_EMPLOYEE_ROLES = [USER_ROLES.ADMIN, USER_ROLES.CLIENT, USER_ROLES.APROBADOR] as const;

export const USER_ROLE_LABELS = {
  root: 'Root',
  admin: 'Admin Empleados',
  client: 'Empleado',
  aprobador: 'Aprobador Empleado',
  admin_tenant: 'Admin Clientes',
} as const;

// Etiquetas de rol para mostrar en UI (RoleSwitcher, chips, encabezados).
// Los roles operativos por empresa (admin_tenant, admin, client, aprobador)
// viven en user_tenant_roles; ver User::ROLE_PRIORITY en el backend.
export const USER_ROLE_DISPLAY_LABELS = {
  root: 'Administrador Plataforma',
  admin: 'Admin Empleados',
  client: 'Empleado',
  aprobador: 'Aprobador Empleado',
  admin_tenant: 'Admin Clientes',
} as const;

// Jerarquía RBAC de "quién puede ASIGNAR qué rol a quién" al crear/editar un
// usuario DENTRO de una misma empresa. Espeja backend:
// UserService::ASSIGNABLE_ROLES (fuente única de verdad). root NO aparece
// como clave: acceso total, no consulta este mapa.
// Usado por:
//   - TenantAssignmentCard.tsx (qué roles puede asignar el actor al crear/
//     editar un usuario en una empresa).
// NOTA (Observación 1, 2026-08): esta jerarquía de ASIGNACIÓN ya no es la
// misma que la de ADMINISTRACIÓN — ver MANAGEABLE_ROLES_BY_ACTOR abajo, que
// usa canEditTarget()/UsersListPage.tsx. Un admin_tenant sigue pudiendo
// asignar el rol admin aquí, pero ya no puede administrar (editar, resetear
// contraseña) una cuenta admin existente.
export const ASSIGNABLE_ROLES_BY_ACTOR: Record<string, string[]> = {
  admin_tenant: [USER_ROLES.ADMIN, USER_ROLES.APROBADOR, USER_ROLES.CLIENT],
  admin: [USER_ROLES.APROBADOR, USER_ROLES.CLIENT],
};

// Jerarquía RBAC de "quién puede ADMINISTRAR (editar, resetear contraseña) a
// quién" DENTRO de una misma empresa. Espeja backend:
// UserService::MANAGEABLE_ROLES (fuente única de verdad; ver también
// UserService::canManageUser — Decisión C1 del plan de observaciones del
// cliente 2026-07, endurecida por Observación 1 2026-08). root, admin_tenant
// y admin NO aparecen como target de nadie no-root; se resuelven aparte:
//   - root: acceso total, no consulta este mapa.
//   - admin_tenant y admin como TARGET: intocables para cualquier no-root
//     (regla 3 de canManageUser, extendida por Observación 1 a también
//     bloquear target admin en cualquier empresa), sin importar el rol del
//     actor.
// Usado por:
//   - userPermissions.ts (canEditTarget(), consumido por UsersListPage.tsx
//     y UserDetailPage.tsx para gatear edición/reset de contraseña).
export const MANAGEABLE_ROLES_BY_ACTOR: Record<string, string[]> = {
  admin_tenant: [USER_ROLES.APROBADOR, USER_ROLES.CLIENT],
  admin: [USER_ROLES.APROBADOR, USER_ROLES.CLIENT],
};

// Roles asignables POR EMPRESA en la carga masiva de usuarios (columna
// "Rol en empresa {n}" del Excel). No existe un rol de nivel de fila: el rol
// siempre vive en la empresa (user_tenant_roles). Debe coincidir EXACTAMENTE
// con las reglas de validación del backend:
//   - UploadUserBatchDataRequest::rules() -> 'users.*.organizaciones.*.roles.*'
//   - UsersImport::ALLOWED_ORG_ROLES
// 'root' queda excluido a propósito: es un rol global, no se asigna por
// empresa, y solo un root puede dar de alta a otro root (jerarquía que la
// carga masiva no puede verificar por fila), así que los root se crean a mano
// desde el alta individual.
export const BULK_UPLOAD_ORG_ROLES = [
  USER_ROLES.CLIENT,
  USER_ROLES.ADMIN,
  USER_ROLES.ADMIN_TENANT,
  USER_ROLES.APROBADOR,
] as const;

// User Status
export const USER_STATUS = {
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  PENDING: 'pending',
} as const;

export const USER_STATUS_LABELS = {
  active: 'Activo',
  inactive: 'Inactivo',
  pending: 'Pendiente',
} as const;

// Pagination
export const DEFAULT_PAGE_SIZE = 10;
export const PAGE_SIZE_OPTIONS = [10, 20, 50, 100];

// API
export const API_TIMEOUT = 30000;
export const API_RETRY_ATTEMPTS = 3;

// UI Messages
export const UI_MESSAGES = {
  // Common
  LOADING: 'Cargando...',
  NO_DATA: 'No hay datos disponibles',
  ERROR: 'Ocurrió un error',
  SUCCESS: 'Operación exitosa',
  CONFIRM: 'Confirmar',
  CANCEL: 'Cancelar',
  SAVE: 'Guardar',
  EDIT: 'Editar',
  DELETE: 'Eliminar',
  BACK: 'Volver',
  SEARCH: 'Buscar',
  FILTER: 'Filtrar',

  // Auth
  LOGIN: 'Iniciar Sesión',
  LOGOUT: 'Cerrar Sesión',
  FORGOT_PASSWORD: 'Olvidé mi contraseña',
  RESET_PASSWORD: 'Restablecer Contraseña',

  // Users
  USERS: 'Usuarios',
  USER: 'Usuario',
  NEW_USER: 'Nuevo Usuario',
  EDIT_USER: 'Editar Usuario',
  USER_DETAIL: 'Detalle de Usuario',
  ACTIVATE_USER: 'Activar',
  DEACTIVATE_USER: 'Desactivar',

  // Tenants
  TENANTS: 'Empresas',
  TENANT: 'Empresa',
  NEW_TENANT: 'Nueva Empresa',
  EDIT_TENANT: 'Editar Empresa',

  // Documents
  DOCUMENTS: 'Documentos',
  MY_DOCUMENTS: 'Mis Documentos',
  UPLOAD_DOCUMENTS: 'Cargar Documentos',
  DOWNLOAD_DOCUMENT: 'Descargar',
  SIGN_DOCUMENT: 'Firmar',

  // Dashboard
  DASHBOARD: 'Dashboard',
  ADMIN_DASHBOARD: 'Panel de Administración',

  // Profile
  PROFILE: 'Perfil',
  MY_ACCOUNT: 'Mi Cuenta',
  SETTINGS: 'Configuración',

  // Notifications
  NOTIFICATIONS: 'Notificaciones',
  NO_NOTIFICATIONS: 'No tienes notificaciones nuevas',
} as const;

// Validation Messages
export const VALIDATION_MESSAGES = {
  REQUIRED: 'Este campo es requerido',
  INVALID_EMAIL: 'Email inválido',
  INVALID_DNI: 'El DNI debe tener 8 dígitos',
  INVALID_RUC: 'El RUC debe tener 11 dígitos',
  MIN_LENGTH: (min: number) => `Mínimo ${min} caracteres`,
  MAX_LENGTH: (max: number) => `Máximo ${max} caracteres`,
  PASSWORD_MISMATCH: 'Las contraseñas no coinciden',
} as const;

// Toast Messages
export const TOAST_MESSAGES = {
  // Success
  USER_CREATED: 'Usuario creado exitosamente',
  USER_UPDATED: 'Usuario actualizado exitosamente',
  USER_DELETED: 'Usuario eliminado exitosamente',
  USER_ACTIVATED: 'Usuario activado exitosamente',
  USER_DEACTIVATED: 'Usuario desactivado exitosamente',

  TENANT_CREATED: 'Empresa creada exitosamente',
  TENANT_UPDATED: 'Empresa actualizada exitosamente',
  TENANT_DELETED: 'Empresa eliminada exitosamente',

  DOCUMENT_UPLOADED: 'Documento cargado exitosamente',
  DOCUMENT_SIGNED: 'Documento firmado exitosamente',
  DOCUMENT_DELETED: 'Documento eliminado exitosamente',

  // Errors
  USER_NOT_FOUND: 'Usuario no encontrado',
  TENANT_NOT_FOUND: 'Empresa no encontrada',
  DOCUMENT_NOT_FOUND: 'Documento no encontrado',

  ERROR_LOADING: 'Error al cargar',
  ERROR_SAVING: 'Error al guardar',
  ERROR_DELETING: 'Error al eliminar',

  FORM_ERRORS: 'Por favor corrige los errores del formulario',
} as const;

// Navigation
export const ROUTES = {
  HOME: '/',
  LOGIN: '/login',
  FORGOT_PASSWORD: '/forgot-password',
  RESET_PASSWORD: '/reset-password',
  FORCE_CHANGE_PASSWORD: '/force-change-password',

  ADMIN: '/admin',
  DASHBOARD: '/dashboard',
  PROFILE: '/profile',
  SETTINGS: '/settings',

  USERS: '/users',
  USERS_NEW: '/users/new',
  USERS_EDIT: (id: string) => `/users/${id}/edit`,
  USERS_DETAIL: (id: string) => `/users/${id}`,

  TENANTS: '/tenants',
  TENANTS_NEW: '/tenants/new',
  TENANTS_EDIT: (id: string) => `/tenants/${id}/edit`,

  DOCUMENTS: '/documents',
  DOCUMENTS_UPLOAD: '/upload',

  BATCHES: '/batches',
  BATCHES_DETAIL: (id: string) => `/batches/${id}`,
} as const;

// UI Navigation Labels
export const NAV_LABELS = {
  DASHBOARD: 'Dashboard',
  MY_DOCUMENTS: 'Mis Documentos',
  UPLOAD_DOCUMENTS: 'Cargar Documentos',
  USERS: 'Usuarios',
  TENANTS: 'Empresas',
  DOCUMENTS: 'Documentos',
  BATCHES: 'Historial de Carga',
  SETTINGS: 'Configuración',
  REPORTS: 'Reportes',
} as const;
