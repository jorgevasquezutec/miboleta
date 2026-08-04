import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'> | null;
    }
}

window.Pusher = Pusher;

/**
 * Laravel Echo configuration for WebSocket connections.
 * Uses Laravel Reverb as the WebSocket server.
 * 
 * For EKS multi-node support:
 * - All nodes connect to the same Redis pub/sub
 * - Reverb instances share state through Redis
 * - Broadcasting is consistent across all pods
 * 
 * Authentication: Uses cookies (withCredentials) for auth.
 */
export function createEchoInstance(): Echo<'reverb'> {
    const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8090/api';

    // Dónde conectar el WebSocket. Por defecto, EL MISMO ORIGEN de la página:
    // nginx ya proxya /app hacia Reverb, así que funciona en cualquier
    // despliegue sin reconstruir la imagen.
    //
    // Antes se usaba VITE_REVERB_HOST directamente, y esas variables se
    // hornean en el bundle al construir. La imagen publicada la construye CI
    // con el dominio del servidor del proveedor, así que en la instalación de
    // un cliente el navegador intentaba abrir el WebSocket contra un servidor
    // ajeno — y al cambiar el cliente su REVERB_APP_KEY, ni siquiera coincidía
    // la clave. Las notificaciones en tiempo real no podían funcionar.
    //
    // Con HTTPS esto además era obligatorio: una página https con un
    // WebSocket ws:// la bloquea el navegador por contenido mixto.
    const enNavegador = typeof window !== 'undefined';
    const paginaEsSegura = enNavegador && window.location.protocol === 'https:';

    // Las VITE_* siguen mandando si están definidas: hacen falta en desarrollo,
    // donde Vite sirve en un puerto y Reverb escucha en otro.
    const hostConfigurado = import.meta.env.VITE_REVERB_HOST;
    const puertoConfigurado = Number(import.meta.env.VITE_REVERB_PORT);

    const wsHost = hostConfigurado || (enNavegador ? window.location.hostname : 'localhost');
    const wsPort = puertoConfigurado
        || (enNavegador && window.location.port ? Number(window.location.port) : (paginaEsSegura ? 443 : 80));
    const usarTLS = import.meta.env.VITE_REVERB_SCHEME
        ? import.meta.env.VITE_REVERB_SCHEME === 'https'
        : paginaEsSegura;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY || 'miboleta-key',
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS: usarTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
        auth: {
            headers: {
                Accept: 'application/json',
            },
        },
        // Use cookies for authentication (works with Sanctum)
        authorizer: (channel: { name: string }) => {
            return {
                authorize: (socketId: string, callback: (error: boolean, data: object) => void) => {
                    fetch(`${apiBaseUrl}/broadcasting/auth`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        credentials: 'include', // Include cookies
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('Auth failed');
                            }
                            return response.json();
                        })
                        .then((data) => {
                            callback(false, data);
                        })
                        .catch((error) => {
                            console.error('[Echo] Auth error:', error);
                            callback(true, {});
                        });
                },
            };
        },
    });

    console.log('[Echo] Created Echo instance', {
        host: import.meta.env.VITE_REVERB_HOST || 'localhost',
        port: import.meta.env.VITE_REVERB_PORT || 8085,
        authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
    });

    return echo;
}

/**
 * Disconnect and cleanup Echo instance.
 */
export function disconnectEcho(): void {
    if (window.Echo) {
        window.Echo.disconnect();
        window.Echo = null;
        console.log('[Echo] Disconnected');
    }
}

export default createEchoInstance;
