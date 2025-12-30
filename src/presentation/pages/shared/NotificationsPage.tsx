import { useEffect, useState } from "react";
import { useDocumentTitle } from "@/presentation/hooks";
import { useNavigate } from "react-router-dom";
import {
    Bell,
    FileText,
    FileCheck,
    Calendar,
    CalendarCheck,
    CalendarX,
    Clock,
    CheckCircle,
    XCircle,
    Loader2,
    Check,
    CheckCheck,
    Trash2,
    Filter,
    ArrowLeft,
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/presentation/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/presentation/components/ui/select";
import { useNotificationsStore } from "@/presentation/stores/notificationsStore";
import { Notification } from "@/core/domain/entities";
import { cn } from "@/presentation/components/ui/utils";

// Icon mapping based on notification type
const getNotificationIcon = (type: string) => {
    switch (type) {
        case "document.new":
            return FileText;
        case "document.signed":
            return FileCheck;
        case "vacation.created":
            return Calendar;
        case "vacation.approved":
            return CalendarCheck;
        case "vacation.rejected":
            return CalendarX;
        case "vacation.pending_confirmation":
            return Clock;
        case "vacation.taken":
            return CheckCircle;
        case "vacation.not_taken":
            return XCircle;
        default:
            return Bell;
    }
};

// Color/style based on type
const getNotificationStyle = (type: string) => {
    if (type.startsWith("document")) {
        return "text-blue-600 bg-blue-100";
    }
    if (type === "vacation.approved" || type === "vacation.taken") {
        return "text-green-600 bg-green-100";
    }
    if (type === "vacation.rejected" || type === "vacation.not_taken") {
        return "text-red-600 bg-red-100";
    }
    if (type === "vacation.created" || type === "vacation.pending_confirmation") {
        return "text-orange-600 bg-orange-100";
    }
    return "text-gray-600 bg-gray-100";
};

interface NotificationCardProps {
    notification: Notification;
    onMarkAsRead: (id: number) => void;
    onDelete: (id: number) => void;
    onClick: (notification: Notification) => void;
}

function NotificationCard({ notification, onMarkAsRead, onDelete, onClick }: NotificationCardProps) {
    const Icon = getNotificationIcon(notification.type);
    const iconStyle = getNotificationStyle(notification.type);

    return (
        <div
            className={cn(
                "flex items-start gap-4 p-4 rounded-lg border cursor-pointer transition-all",
                notification.is_read
                    ? "bg-white border-gray-200 hover:border-gray-300"
                    : "bg-blue-50 border-blue-200 hover:border-blue-300"
            )}
            onClick={() => onClick(notification)}
        >
            <div className={cn("p-3 rounded-full flex-shrink-0", iconStyle)}>
                <Icon className="w-5 h-5" />
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                    <p className={cn(
                        "text-sm",
                        notification.is_read ? "text-gray-900" : "font-semibold text-gray-900"
                    )}>
                        {notification.title}
                    </p>
                    <span className="text-xs text-gray-400 whitespace-nowrap flex-shrink-0">
                        {notification.time_ago}
                    </span>
                </div>
                {notification.message && (
                    <p className="text-sm text-gray-600 mt-1">
                        {notification.message}
                    </p>
                )}
                <div className="flex items-center gap-2 mt-3">
                    <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                        {notification.type_label}
                    </span>
                    {!notification.is_read && (
                        <button
                            type="button"
                            className="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1"
                            onClick={(e) => {
                                e.stopPropagation();
                                onMarkAsRead(notification.id);
                            }}
                        >
                            <Check className="w-3 h-3" />
                            Marcar como leída
                        </button>
                    )}
                    <button
                        type="button"
                        className="text-xs text-gray-400 hover:text-red-600 flex items-center gap-1 ml-auto"
                        onClick={(e) => {
                            e.stopPropagation();
                            onDelete(notification.id);
                        }}
                    >
                        <Trash2 className="w-3 h-3" />
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    );
}

export function NotificationsPage() {
    useDocumentTitle('Notificaciones');
    const navigate = useNavigate();
    const {
        notifications,
        unreadCount,
        isLoading,
        page,
        totalPages,
        total,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        deleteNotification,
    } = useNotificationsStore();

    const [filter, setFilter] = useState<string>("all");

    // Fetch notifications on mount
    useEffect(() => {
        fetchNotifications({
            perPage: 20,
            unread_only: filter === "unread"
        });
    }, [fetchNotifications, filter]);

    const handleNotificationClick = (notification: Notification) => {
        // Mark as read if not already
        if (!notification.is_read) {
            markAsRead(notification.id);
        }

        // Navigate to action URL if present
        if (notification.action_url) {
            navigate(notification.action_url);
        }
    };

    const handleLoadMore = () => {
        fetchNotifications({
            page: page + 1,
            perPage: 20,
            unread_only: filter === "unread"
        });
    };

    const handleMarkAllAsRead = () => {
        markAllAsRead();
    };

    return (
        <div className="max-w-3xl mx-auto space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => navigate(-1)}
                >
                    <ArrowLeft className="w-5 h-5" />
                </Button>
                <div className="flex-1">
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Notificaciones</h1>
                    <p className="text-sm text-gray-500">
                        {unreadCount > 0
                            ? `Tienes ${unreadCount} notificación${unreadCount > 1 ? 'es' : ''} sin leer`
                            : 'No tienes notificaciones nuevas'
                        }
                    </p>
                </div>
            </div>

            {/* Controls */}
            <Card>
                <CardContent className="py-4">
                    <div className="flex items-center justify-between gap-4 flex-wrap">
                        <div className="flex items-center gap-2">
                            <Filter className="w-4 h-4 text-gray-400" />
                            <Select value={filter} onValueChange={setFilter}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Filtrar por..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todas</SelectItem>
                                    <SelectItem value="unread">Solo no leídas</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {unreadCount > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleMarkAllAsRead}
                                className="text-blue-600 border-blue-200 hover:bg-blue-50"
                            >
                                <CheckCheck className="w-4 h-4 mr-2" />
                                Marcar todas como leídas
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Notifications List */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg flex items-center gap-2">
                        <Bell className="w-5 h-5" />
                        {filter === "unread" ? "Notificaciones no leídas" : "Todas las notificaciones"}
                        <span className="text-sm font-normal text-gray-500">
                            ({total} en total)
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {isLoading && notifications.length === 0 ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
                        </div>
                    ) : notifications.length === 0 ? (
                        <div className="text-center py-12">
                            <Bell className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-1">
                                No hay notificaciones
                            </h3>
                            <p className="text-gray-500">
                                {filter === "unread"
                                    ? "¡Estás al día! No tienes notificaciones sin leer."
                                    : "Cuando recibas notificaciones, aparecerán aquí."
                                }
                            </p>
                        </div>
                    ) : (
                        <>
                            {notifications.map((notification) => (
                                <NotificationCard
                                    key={notification.id}
                                    notification={notification}
                                    onMarkAsRead={markAsRead}
                                    onDelete={deleteNotification}
                                    onClick={handleNotificationClick}
                                />
                            ))}

                            {/* Load More */}
                            {page < totalPages && (
                                <div className="text-center pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={handleLoadMore}
                                        disabled={isLoading}
                                    >
                                        {isLoading ? (
                                            <>
                                                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                                Cargando...
                                            </>
                                        ) : (
                                            'Ver más notificaciones'
                                        )}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default NotificationsPage;
