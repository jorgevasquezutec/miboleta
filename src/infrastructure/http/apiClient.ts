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

// Interceptor de Request - Agregar tenant header
apiClient.interceptors.request.use(
  (config) => {
    // Obtener tenant actual del localStorage
    const authStorage = localStorage.getItem('auth-storage');
    if (authStorage) {
      try {
        const { state } = JSON.parse(authStorage);
        const currentTenantId = state?.currentTenant?.id;

        // Agregar X-Tenant-ID header si hay tenant seleccionado
        if (currentTenantId) {
          config.headers['X-Tenant-ID'] = currentTenantId;
        }
      } catch (error) {
        console.error('Error parsing auth storage:', error);
      }
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor de Response - Manejar errores y refresh token
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  async (error: AxiosError) => {
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
          // Refresh falló - limpiar storage y redirigir a login
          isRefreshing = false;
          processQueue(new Error('Token refresh failed'), null);
          
          console.error('Refresh token failed - redirecting to login');
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
