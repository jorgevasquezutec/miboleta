import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';
import { getCsrfToken } from '@/shared/utils';

// Configuración base de la API
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8090/api';
// Crear instancia de Axios
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 10000, // 10 segundos
  withCredentials: true, // Importante: permite enviar/recibir cookies HttpOnly
});

// Variable para controlar si ya estamos refrescando el token
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: unknown) => void;
  reject: (reason?: unknown) => void;
}> = [];

// Función para procesar la cola de requests fallidos
const processQueue = (error: Error | null, token: string | null = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });

  failedQueue = [];
};

// ✅ OPTIMIZACIÓN: Request queue para evitar requests duplicados
const requestQueue = new Map<string, Promise<any>>();

// Interceptor de Request - Agregar tenant headers, CSRF token, y deduplicación
apiClient.interceptors.request.use(
  (config) => {
    // Agregar CSRF token si está disponible
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      config.headers['X-XSRF-TOKEN'] = csrfToken;
    }

    // ✅ NUEVO: Usar tenantFilterStore para filtrado de tenants
    try {
      // Importar dinámicamente para evitar circular dependency
      const tenantFilterStorage = localStorage.getItem('tenant-filter-storage');
      const authStorage = localStorage.getItem('auth-storage');

      // Usuario en sesión: sus empresas acotan lo que puede pedir el filtro.
      // Root es la excepción: puede filtrar por cualquier empresa (el backend
      // no valida su header en TenantFilter).
      const authUser = authStorage ? JSON.parse(authStorage)?.state?.user : null;
      const isRootUser = authUser?.role === 'root';
      const ownTenantIds: string[] = (authUser?.tenants ?? []).map((t: any) => String(t.id));

      if (tenantFilterStorage) {
        const { state } = JSON.parse(tenantFilterStorage);
        const filter = state?.filter;

        // Red de seguridad: un filtro sellado a nombre de otro usuario se
        // ignora aquí aunque el store todavía no lo haya descartado.
        const filterOwnerId = state?.ownerUserId ?? null;
        const belongsToCurrentUser =
          authUser?.id !== undefined && filterOwnerId !== null && String(filterOwnerId) === String(authUser.id);

        if (filter && belongsToCurrentUser) {
          // ✅ Filtro activo: enviar tenant IDs
          if (filter.mode !== 'all' && filter.tenantIds && filter.tenantIds.length > 0) {
            // Solo se piden empresas del usuario: si el filtro arrastra alguna
            // ajena, se descarta en vez de provocar un 403 del backend.
            const requestedIds: string[] = filter.tenantIds.map((id: any) => String(id));
            const permittedIds = isRootUser
              ? requestedIds
              : requestedIds.filter(id => ownTenantIds.includes(id));

            if (permittedIds.length > 0) {
              const tenantQuery = permittedIds.join(',');
              config.headers['X-Tenant-Ids'] = tenantQuery;

              console.log(`🏢 [API] Filtering by tenants: ${tenantQuery} (mode: ${filter.mode})`);
            } else {
              console.warn(
                `🏢 [API] Filtro descartado: las empresas ${requestedIds.join(',')} no son del usuario`
              );
            }
          } else if (filter.mode === 'all') {
            // Check user role: only root can send X-Tenant-Scope: all
            const userRole: string | undefined = authUser?.role;

            if (userRole === 'root') {
              config.headers['X-Tenant-Scope'] = 'all';
              console.log(`🏢 [API] Root user - no tenant filter (showing all companies)`);
            } else if (ownTenantIds.length > 0) {
              // Non-root users: send their assigned tenant IDs instead of scope=all
              const tenantIds = ownTenantIds.join(',');
              config.headers['X-Tenant-Ids'] = tenantIds;
              console.log(`🏢 [API] Non-root 'all' mode - sending user tenants: ${tenantIds}`);
            }
          }
        }
      }

      // ✅ Si no hay X-Tenant-Ids configurado, usar el tenant del usuario autenticado
      // PERO solo si el usuario NO es root (root debe ver TODO sin filtro)
      if (!config.headers['X-Tenant-Ids'] && !config.headers['X-Tenant-Scope'] && authUser) {
        // ⚠️ Si es usuario root SIN filtro explícito, NO enviar header (verá todo)
        if (isRootUser) {
          console.log(`🏢 [API] Root user without filter - showing ALL tenants`);
        } else if (ownTenantIds.length > 0) {
          // Usuarios no-root: usar sus tenants como fallback
          const tenantIds = ownTenantIds.join(',');
          config.headers['X-Tenant-Ids'] = tenantIds;
          console.log(`🏢 [API] Using user's tenants: ${tenantIds}`);
        }
      }
    } catch (error) {
      console.error('❌ [API] Error parsing tenant filter storage:', error);
    }

    // ✅ OPTIMIZACIÓN: Deduplicación de requests idénticos
    const cacheKey = `${config.method}:${config.url}:${config.headers['X-Tenant-Ids'] || 'none'}`;

    // Si ya existe un request idéntico en progreso, reutilizarlo
    if (requestQueue.has(cacheKey)) {
      console.log(`🔄 [Cache] Reusing in-flight request: ${cacheKey}`);
      // Retornar la promise existente
      return requestQueue.get(cacheKey)!.then(() => config);
    }

    return config;
  },
  (error) => {
    console.error('❌ [Request Error]', error);
    return Promise.reject(error);
  }
);

// Interceptor de Response - Manejar errores, refresh token, y limpiar request queue
apiClient.interceptors.response.use(
  (response) => {
    // ✅ Limpiar del request queue cuando completa exitosamente
    const cacheKey = `${response.config.method}:${response.config.url}:${response.config.headers?.['X-Tenant-Ids'] || 'none'}`;
    requestQueue.delete(cacheKey);

    return response;
  },
  async (error: AxiosError) => {
    // ✅ Limpiar del request queue cuando falla
    if (error.config) {
      const cacheKey = `${error.config.method}:${error.config.url}:${error.config.headers?.['X-Tenant-Ids'] || 'none'}`;
      requestQueue.delete(cacheKey);
    }

    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };


    // Manejo de errores HTTP
    if (error.response) {
      const status = error.response.status;

      // Error 401: Token expirado o inválido
      if (status === 401 && originalRequest && !originalRequest._retry) {
        // Si ya estamos intentando refrescar, agregar request a la cola
        if (isRefreshing) {
          return new Promise((resolve, reject) => {
            failedQueue.push({ resolve, reject });
          }).then(() => {
            return apiClient(originalRequest);
          }).catch(err => {
            return Promise.reject(err);
          });
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
          // Intentar refrescar el token
          await axios.post(
            `${API_BASE_URL}/refresh`,
            {},
            { withCredentials: true }
          );

          // Token refrescado exitosamente
          isRefreshing = false;
          processQueue(null);

          // Reintentar el request original
          return apiClient(originalRequest);
        } catch (refreshError) {
          console.error('❌ [Refresh Failed] Token refresh failed:', refreshError);

          // Refresh falló - limpiar storage y redirigir a login
          isRefreshing = false;
          processQueue(new Error('Token refresh failed'), null);

          localStorage.removeItem('auth-storage');
          // El filtro de empresa se va con la sesión: si se quedara, el
          // siguiente usuario de este navegador heredaría estas empresas y el
          // backend le respondería 403 en todas sus peticiones.
          localStorage.removeItem('tenant-filter-storage');

          // Solo redirigir si no estamos ya en login
          if (window.location.pathname !== '/login') {
            window.location.href = '/login';
          }

          return Promise.reject(toApiError(refreshError));
        }
      }

      // Otros errores HTTP
      switch (status) {
        case 403:
          // Prohibido - usuario no tiene permisos
          console.error('Forbidden - insufficient permissions');
          break;

        case 404:
          console.error('Resource not found');
          break;

        case 422:
          // Validation errors
          console.error('Validation error:', error.response.data);
          break;

        case 500:
          console.error('Internal server error');
          break;

        default:
          console.error(`HTTP Error ${status}:`, error.response.data);
      }
    } else if (error.request) {
      // Request fue hecho pero no hubo respuesta
      console.error('No response from server:', error.request);
    } else {
      // Error en la configuración del request
      console.error('Request error:', error.message);
    }

    // Normalización única: a partir de aquí NADIE fuera de este archivo
    // interpreta la respuesta cruda de axios. Todo consumidor recibe un
    // `ApiError` con el mensaje de resumen, la lista completa de mensajes y el
    // mapa de errores por campo (y `data` con el payload crudo por si hace
    // falta algo específico, como el Blob de los exports).
    return Promise.reject(toApiError(error));
  }
);

export default apiClient;

/**
 * Aplana TODOS los mensajes de TODOS los campos de un `errors` de Laravel, en
 * el orden en que llegan (un campo puede traer varios mensajes). A
 * diferencia de `getFieldErrors`, que solo se queda con el primero de cada
 * campo para pintarlo junto al input.
 */
const flattenFieldErrors = (errors: Record<string, unknown>): string[] => {
  const flattened: string[] = [];
  for (const messages of Object.values(errors)) {
    if (Array.isArray(messages)) {
      for (const message of messages) {
        if (typeof message === 'string') flattened.push(message);
      }
    } else if (typeof messages === 'string') {
      flattened.push(messages);
    }
  }
  return flattened;
};

// Helper para extraer mensaje de error
export const getErrorMessage = (error: unknown): string => {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    const data = error.response?.data;

    // REGLA CLAVE: en 422 con `errors`, el resumen sale de ahí, nunca de
    // `data.message` (que trae el sufijo "(and N more errors)" que agrega
    // ValidationException::summarize() en Laravel). Sin esto, el usuario ve
    // "Este número de documento ya está registrado (and 1 more error)" en
    // vez de los dos mensajes reales.
    if (status === 422 && data?.errors && typeof data.errors === 'object') {
      const flattened = flattenFieldErrors(data.errors);
      if (flattened.length > 0) return flattened[0];
    }

    // Error específico de la API (algunos endpoints retornan { "error": "mensaje" })
    if (data?.error) {
      return data.error;
    }

    // Error de Axios con respuesta del servidor
    if (data?.message) {
      return data.message;
    }

    // Error de validación Laravel (formato sin `data.message`, p.ej. una
    // respuesta de empresas antes del alineamiento de formato)
    if (data?.errors) {
      const errors = data.errors;
      const firstError = Object.values(errors)[0];
      if (Array.isArray(firstError) && firstError.length > 0) {
        return firstError[0] as string;
      }
    }

    // Error genérico de Axios
    return error.message;
  }

  // Error genérico
  if (error instanceof Error) {
    return error.message;
  }

  return 'An unexpected error occurred';
};

/**
 * Errores de validación de Laravel (422) por campo: `{ email: 'mensaje' }`.
 *
 * Se queda con el primer mensaje de cada campo, que es el que interesa pintar
 * junto al input. Devuelve `{}` si la respuesta no trae errores por campo.
 */
export const getFieldErrors = (error: unknown): Record<string, string> => {
  if (!axios.isAxiosError(error)) return {};

  const errors = error.response?.data?.errors;
  if (!errors || typeof errors !== 'object') return {};

  return Object.entries(errors as Record<string, unknown>).reduce<Record<string, string>>(
    (acc, [field, messages]) => {
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (typeof message === 'string') acc[field] = message;
      return acc;
    },
    {}
  );
};

/** Categoría del error, derivada del status HTTP (o su ausencia). */
export type ApiErrorCode = 'validation' | 'forbidden' | 'not_found' | 'server' | 'network' | 'unknown';

/**
 * Error normalizado de la API. Ya no hace falta mirar `err.response.data` en
 * cada catch: `status`/`code` dicen qué pasó, `messages` trae TODOS los
 * mensajes aplanados (nunca vacío) y `fieldErrors` los de validación por
 * campo, listos para pintar junto al input. `data` conserva el payload crudo
 * como red de seguridad para consumidores que aún no migraron.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly code: ApiErrorCode,
    public readonly messages: string[],
    public readonly fieldErrors: Record<string, string> = {},
    public readonly status?: number,
    public readonly data?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/**
 * Convierte cualquier error en un `ApiError` normalizado. Idempotente: si
 * `error` ya es un `ApiError` (p.ej. un repositorio que ya lo lanzó), lo
 * devuelve tal cual en vez de volver a envolverlo.
 */
export const toApiError = (error: unknown): ApiError => {
  if (error instanceof ApiError) return error;

  if (axios.isAxiosError(error)) {
    const response = error.response;

    // Sin `error.response`: no hubo respuesta del servidor (timeout, sin
    // conexión, CORS). Es la única situación con `status` indefinido.
    if (!response) {
      const message = error.message || 'No se pudo conectar con el servidor';
      return new ApiError(message, 'network', [message]);
    }

    const status = response.status;
    const data = response.data;
    const fieldErrors = getFieldErrors(error);

    // Mismo criterio que getErrorMessage: en 422 con `errors`, los mensajes
    // vienen de ahí, aplanados TODOS (no solo el primero de cada campo).
    const validationMessages =
      status === 422 && data?.errors && typeof data.errors === 'object'
        ? flattenFieldErrors(data.errors)
        : [];

    const messages = validationMessages.length > 0 ? validationMessages : [getErrorMessage(error)];

    const code: ApiErrorCode =
      status === 422 ? 'validation'
      : status === 403 ? 'forbidden'
      : status === 404 ? 'not_found'
      : status >= 500 ? 'server'
      : 'unknown';

    return new ApiError(messages[0], code, messages, fieldErrors, status, data);
  }

  const message = error instanceof Error ? error.message : 'An unexpected error occurred';
  return new ApiError(message, 'unknown', [message]);
};

/**
 * Initialize CSRF protection by fetching CSRF cookie from Laravel
 * This should be called once when the app starts
 */
export const initializeCsrf = async (): Promise<void> => {
  try {
    const baseUrl = API_BASE_URL.replace('/api', '');
    await axios.get(`${baseUrl}/sanctum/csrf-cookie`, {
      withCredentials: true,
    });
  } catch (error) {
    console.error('❌ [CSRF] Failed to initialize cookie:', error);
  }
};
