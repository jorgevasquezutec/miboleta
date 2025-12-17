import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { ReactNode } from 'react';

/**
 * Cliente de React Query configurado con opciones óptimas
 * 
 * Configuración:
 * - staleTime: 5 minutos - Los datos se consideran frescos por 5 min
 * - gcTime: 10 minutos - Los datos en cache se eliminan después de 10 min
 * - retry: 1 - Solo reintenta una vez en caso de error
 * - refetchOnWindowFocus: true - Refresca al volver a la ventana
 * - refetchOnReconnect: true - Refresca al reconectar
 */
export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            // Tiempo que los datos se consideran frescos (no se refetchean)
            staleTime: 5 * 60 * 1000, // 5 minutos

            // Tiempo que los datos permanecen en cache después de no usarse
            gcTime: 10 * 60 * 1000, // 10 minutos (antes era cacheTime)

            // Reintentos en caso de error
            retry: 1,

            // Refetch en eventos del navegador
            refetchOnWindowFocus: true,
            refetchOnReconnect: true,
            refetchOnMount: true,

            // No refetch al cambiar de tab si los datos son frescos
            refetchInterval: false,
        },
        mutations: {
            // Reintentos para mutaciones
            retry: 0, // No reintentar mutaciones por defecto
        },
    },
});

interface QueryProviderProps {
    children: ReactNode;
}

/**
 * Provider de React Query para toda la aplicación
 * 
 * Incluye:
 * - QueryClient configurado
 * - DevTools en desarrollo
 * 
 * @example
 * ```tsx
 * // En App.tsx o main.tsx
 * <QueryProvider>
 *   <App />
 * </QueryProvider>
 * ```
 */
export function QueryProvider({ children }: QueryProviderProps) {
    return (
        <QueryClientProvider client={queryClient}>
            {children}
            {/* DevTools solo en desarrollo */}
            {import.meta.env.DEV && (
                <ReactQueryDevtools
                    initialIsOpen={false}
                    position="bottom-right"
                />
            )}
        </QueryClientProvider>
    );
}
