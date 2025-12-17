import apiClient from "@/infrastructure/http/apiClient";
import {
    Notification,
    NotificationFilters,
    PaginatedNotifications
} from "@/core/domain/entities";
import { INotificationRepository } from '@/core/domain/repositories/INotificationRepository';

class NotificationRepositoryClass implements INotificationRepository {

    async findAll(filters?: NotificationFilters): Promise<PaginatedNotifications> {
        const params = new URLSearchParams();

        if (filters?.page) params.append('page', filters.page.toString());
        if (filters?.perPage) params.append('per_page', filters.perPage.toString());
        if (filters?.unread_only) params.append('unread_only', 'true');
        if (filters?.type) params.append('type', filters.type);

        const response = await apiClient.get<{ data: Notification[]; meta: any }>(
            `/notifications?${params.toString()}`
        );

        return {
            data: response.data.data,
            meta: {
                currentPage: response.data.meta.current_page,
                lastPage: response.data.meta.last_page,
                perPage: response.data.meta.per_page,
                total: response.data.meta.total,
            },
        };
    }

    async getUnreadCount(): Promise<number> {
        const response = await apiClient.get<{ count: number }>('/notifications/unread-count');
        return response.data.count;
    }

    async markAsRead(id: number): Promise<Notification> {
        const response = await apiClient.put<{ data: Notification }>(
            `/notifications/${id}/read`
        );
        return response.data.data;
    }

    async markAllAsRead(): Promise<number> {
        const response = await apiClient.put<{ count: number }>(
            '/notifications/read-all'
        );
        return response.data.count;
    }

    async delete(id: number): Promise<void> {
        await apiClient.delete(`/notifications/${id}`);
    }
}

export const notificationRepository = new NotificationRepositoryClass();
