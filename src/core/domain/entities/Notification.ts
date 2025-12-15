export interface Notification {
    id: number;
    type: string;
    type_label: string;
    icon: string;
    title: string;
    message: string | null;
    data: Record<string, any> | null;
    action_url: string | null;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    time_ago: string;
}

export interface NotificationFilters {
    page?: number;
    perPage?: number;
    unread_only?: boolean;
    type?: string;
}

export interface PaginatedNotifications {
    data: Notification[];
    meta: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
}
