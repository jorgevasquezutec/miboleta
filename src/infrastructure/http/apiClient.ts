import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

// Configuración base de la API
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/api';
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

/**
 * Get CSRF token from cookies
 */
function getCsrfToken(): string | null {
  const name = 'XSRF-TOKEN';
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) {
    const token = parts.pop()?.split(';').shift();
    return token ? decodeURIComponent(token) : null;
  }
  return null;
}

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
      if (tenantFilterStorage) {
        const { state } = JSON.parse(tenantFilterStorage);
        const filter = state?.filter;

        if (filter) {
          // ✅ Filtro activo: enviar tenant IDs
          if (filter.mode !== 'all' && filter.tenantIds && filter.tenantIds.length > 0) {
            const tenantQuery = filter.tenantIds.join(',');
            config.headers['X-Tenant-Ids'] = tenantQuery;

            console.log(`🏢 [API] Filtering by tenants: ${tenantQuery} (mode: ${filter.mode})`);
          } else {
            // ✅ Modo 'all': sin filtro (mostrar todas las empresas)
            config.headers['X-Tenant-Scope'] = 'all';
            console.log(`🏢 [API] No tenant filter (showing all companies)`);
          }
        }
      }
    } catch (error) {
      console.error('❌ [API] Error parsing tenant filter storage:', error);
    }

    // ⚠️ RETROCOMPATIBILIDAD: Mantener X-Tenant-Id para backend legacy
    const authStorage = localStorage.getItem('auth-storage');
    if (authStorage) {
      try {
        const { state } = JSON.parse(authStorage);
        const currentTenantId = state?.currentTenant?.id;

        // Solo añadir si no hay X-Tenant-Ids (nuevo header tiene prioridad)
        if (currentTenantId && !config.headers['X-Tenant-Ids']) {
          config.headers['X-Tenant-Id'] = currentTenantId;
        }
      } catch (error) {
        console.error('❌ [API] Error parsing auth storage:', error);
      }
    }

    // ✅ OPTIMIZACIÓN: Deduplicación de requests idénticos
    const cacheKey = `${config.method}:${config.url}:${config.headers['X-Tenant-Ids'] || config.headers['X-Tenant-Id'] || 'none'}`;

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
    const cacheKey = `${response.config.method}:${response.config.url}:${response.config.headers?.['X-Tenant-Ids'] || response.config.headers?.['X-Tenant-Id'] || 'none'}`;
    requestQueue.delete(cacheKey);

    return response;
  },
  async (error: AxiosError) => {
    // ✅ Limpiar del request queue cuando falla
    if (error.config) {
      const cacheKey = `${error.config.method}:${error.config.url}:${error.config.headers?.['X-Tenant-Ids'] || error.config.headers?.['X-Tenant-Id'] || 'none'}`;
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
          const refreshResponse = await axios.post(
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

          // Solo redirigir si no estamos ya en login
          if (window.location.pathname !== '/login') {
            window.location.href = '/login';
          }

          return Promise.reject(refreshError);
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

    return Promise.reject(error);
  }
);

export default apiClient;

// Helper para extraer mensaje de error
export const getErrorMessage = (error: unknown): string => {
  if (axios.isAxiosError(error)) {
    // Error de Axios con respuesta del servidor
    if (error.response?.data?.message) {
      return error.response.data.message;
    }

    // Error de validación Laravel
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
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
