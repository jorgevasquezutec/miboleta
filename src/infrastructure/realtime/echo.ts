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
    const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost/api';

    const echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY || 'miboleta-key',
        wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort: Number(import.meta.env.VITE_REVERB_PORT) || 8085,
        wssPort: Number(import.meta.env.VITE_REVERB_PORT) || 8085,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
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
