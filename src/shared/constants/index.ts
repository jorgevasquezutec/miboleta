/**
 * Application Constants
 */

export const APP_NAME = 'MiBoleta';
export const APP_VERSION = '1.0.0';

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
} as const;

export const USER_ROLE_LABELS = {
  root: 'Root',
  admin: 'Administrador',
  client: 'Usuario',
} as const;

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
