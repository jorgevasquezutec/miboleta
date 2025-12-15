import { create } from "zustand";
import Echo from "laravel-echo";
import { Notification, NotificationFilters } from "@/core/domain/entities";
import { notificationRepository } from "@/infrastructure/persistence/repositories";
import { createEchoInstance, disconnectEcho } from "@/infrastructure/realtime/echo";
import { toast } from "sonner";

interface NotificationsState {
    notifications: Notification[];
    unreadCount: number;
    isLoading: boolean;
    error: string | null;
    isConnected: boolean;

    // Pagination
    page: number;
    totalPages: number;
    total: number;

    // Actions
    fetchNotifications: (filters?: NotificationFilters) => Promise<void>;
    fetchUnreadCount: () => Promise<void>;
    markAsRead: (id: number) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (id: number) => Promise<void>;
    clearError: () => void;

    // Real-time (WebSocket + Polling fallback)
    addNotification: (notification: Notification) => void;
    connectWebSocket: (userId: number) => void;
    disconnectWebSocket: () => void;
    startPolling: () => void;
    stopPolling: () => void;
}

let pollingInterval: ReturnType<typeof setInterval> | null = null;
let echoInstance: Echo<'reverb'> | null = null;

const initialState = {
    notifications: [] as Notification[],
    unreadCount: 0,
    isLoading: false,
    error: null,
    isConnected: false,
    page: 1,
    totalPages: 0,
    total: 0,
};

export const useNotificationsStore = create<NotificationsState>((set, get) => ({
    ...initialState,

    fetchNotifications: async (filters) => {
        set({ isLoading: true, error: null });
        try {
            const result = await notificationRepository.findAll({
                page: filters?.page ?? get().page,
                perPage: filters?.perPage ?? 15,
                unread_only: filters?.unread_only,
                type: filters?.type,
            });

            set({
                notifications: result.data,
                page: result.meta.currentPage,
                totalPages: result.meta.lastPage,
                total: result.meta.total,
                isLoading: false,
            });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al cargar notificaciones';
            set({
                error: errorMessage,
                isLoading: false
            });
        }
    },

    fetchUnreadCount: async () => {
        try {
            const count = await notificationRepository.getUnreadCount();
            set({ unreadCount: count });
        } catch (error: unknown) {
            console.error('Error fetching unread count:', error);
        }
    },

    markAsRead: async (id) => {
        try {
            await notificationRepository.markAsRead(id);
            set((state) => ({
                notifications: state.notifications.map((n) =>
                    n.id === id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n
                ),
                unreadCount: Math.max(0, state.unreadCount - 1),
            }));
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al marcar como leída';
            set({ error: errorMessage });
        }
    },

    markAllAsRead: async () => {
        try {
            await notificationRepository.markAllAsRead();
            set((state) => ({
                notifications: state.notifications.map((n) => ({
                    ...n,
                    is_read: true,
                    read_at: new Date().toISOString(),
                })),
                unreadCount: 0,
            }));
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al marcar todas como leídas';
            set({ error: errorMessage });
        }
    },

    deleteNotification: async (id) => {
        try {
            await notificationRepository.delete(id);
            set((state) => {
                const notification = state.notifications.find((n) => n.id === id);
                const wasUnread = notification && !notification.is_read;
                return {
                    notifications: state.notifications.filter((n) => n.id !== id),
                    unreadCount: wasUnread ? Math.max(0, state.unreadCount - 1) : state.unreadCount,
                    total: state.total - 1,
                };
            });
        } catch (error: unknown) {
            const errorMessage = error instanceof Error ? error.message : 'Error al eliminar notificación';
            set({ error: errorMessage });
        }
    },

    clearError: () => set({ error: null }),

    // Add a notification received from WebSocket
    addNotification: (notification) => {
        set((state) => ({
            notifications: [notification, ...state.notifications],
            unreadCount: state.unreadCount + 1,
            total: state.total + 1,
        }));

        // Show toast notification
        toast.info(notification.title, {
            description: notification.message || undefined,
            action: notification.action_url
                ? {
                    label: "Ver",
                    onClick: () => {
                        window.location.href = notification.action_url!;
                    },
                }
                : undefined,
        });
    },

    // Connect to WebSocket for real-time notifications
    connectWebSocket: (userId) => {
        // Disconnect existing connection if any
        if (echoInstance) {
            disconnectEcho();
        }

        try {
            echoInstance = createEchoInstance();
            window.Echo = echoInstance;

            // Subscribe to private user channel
            echoInstance
                .private(`user.${userId}`)
                .listen('.notification.created', (data: { notification: Notification }) => {
                    console.log('[WebSocket] New notification received:', data.notification);
                    get().addNotification(data.notification);
                })
                .error((error: unknown) => {
                    console.error('[WebSocket] Channel error:', error);
                    set({ isConnected: false });
                    // Fall back to polling on error
                    get().startPolling();
                });

            set({ isConnected: true });
            console.log('[WebSocket] Connected to user channel:', userId);

            // Stop polling since we're connected
            get().stopPolling();

            // Fetch initial count
            get().fetchUnreadCount();

        } catch (error) {
            console.error('[WebSocket] Connection failed:', error);
            set({ isConnected: false });
            // Fall back to polling
            get().startPolling();
        }
    },

    // Disconnect WebSocket
    disconnectWebSocket: () => {
        if (echoInstance) {
            disconnectEcho();
            echoInstance = null;
        }
        set({ isConnected: false });
    },

    // Polling fallback (when WebSocket not available)
    startPolling: () => {
        // Only poll if WebSocket is not connected
        if (get().isConnected) {
            return;
        }

        // Clear existing interval if any
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        console.log('[Polling] Starting notification polling (fallback)');

        // Fetch immediately
        get().fetchUnreadCount();

        // Poll every 30 seconds
        pollingInterval = setInterval(() => {
            get().fetchUnreadCount();
        }, 30000);
    },

    stopPolling: () => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
            console.log('[Polling] Stopped');
        }
    },
}));
