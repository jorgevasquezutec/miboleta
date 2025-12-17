import {
  Notification,
  NotificationFilters,
  PaginatedNotifications
} from '../entities';

export interface INotificationRepository {
  findAll(filters?: NotificationFilters): Promise<PaginatedNotifications>;
  getUnreadCount(): Promise<number>;
  markAsRead(id: number): Promise<Notification>;
  markAllAsRead(): Promise<number>;
  delete(id: number): Promise<void>;
}
