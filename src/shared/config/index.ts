// Shared Configuration
export const API_CONFIG = {
  BASE_URL: import.meta.env.VITE_API_URL || '/api',
  TIMEOUT: 30000,
  MOCK_DELAY: 500, // Para desarrollo con mock API
};

export const STORAGE_KEYS = {
  AUTH_TOKEN: 'miboleta_auth_token',
  USER: 'miboleta_user',
  TENANT: 'miboleta_tenant',
};

export const PAGINATION = {
  DEFAULT_PAGE: 1,
  DEFAULT_PAGE_SIZE: 10,
  MAX_PAGE_SIZE: 100,
  MOCK_DELAY: 500, // Delay para simular red en mock API
};

export const APP_CONFIG = {
  NAME: 'MiBoleta',
  VERSION: '1.0.0',
  LOCALE: 'es-PE',
};
