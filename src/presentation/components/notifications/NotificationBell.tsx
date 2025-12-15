import { useEffect, useState } from "react";
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
} from "lucide-react";
import { Button } from "@/presentation/components/ui/button";
import { Badge } from "@/presentation/components/ui/badge";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/presentation/components/ui/dropdown-menu";
import { ScrollArea } from "@/presentation/components/ui/scroll-area";
import { useNotificationsStore } from "@/presentation/stores/notificationsStore";
import { useAuthStore } from "@/presentation/stores";
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

interface NotificationItemProps {
    notification: Notification;
    onMarkAsRead: (id: number) => void;
    onClick: (notification: Notification) => void;
}

function NotificationItem({ notification, onMarkAsRead, onClick }: NotificationItemProps) {
    const Icon = getNotificationIcon(notification.type);
    const iconStyle = getNotificationStyle(notification.type);

    return (
        <div
            className={cn(
                "flex items-start gap-3 p-3 cursor-pointer transition-colors",
                notification.is_read
                    ? "bg-white hover:bg-gray-50"
                    : "bg-blue-50 hover:bg-blue-100"
            )}
            onClick={() => onClick(notification)}
        >
            <div className={cn("p-2 rounded-full flex-shrink-0", iconStyle)}>
                <Icon className="w-4 h-4" />
            </div>
            <div className="flex-1 min-w-0">
                <p className={cn(
                    "text-sm",
                    notification.is_read ? "text-gray-900" : "font-semibold text-gray-900"
                )}>
                    {notification.title}
                </p>
                {notification.message && (
                    <p className="text-xs text-gray-600 mt-0.5 line-clamp-2">
                        {notification.message}
                    </p>
                )}
                <p className="text-xs text-gray-400 mt-1">
                    {notification.time_ago}
                </p>
            </div>
            {!notification.is_read && (
                <button
                    type="button"
                    className="p-1 hover:bg-white rounded-full flex-shrink-0"
                    onClick={(e) => {
                        e.stopPropagation();
                        onMarkAsRead(notification.id);
                    }}
                    title="Marcar como leída"
                >
                    <Check className="w-4 h-4 text-gray-400 hover:text-green-600" />
                </button>
            )}
        </div>
    );
}

export function NotificationBell() {
    const navigate = useNavigate();
    const { user } = useAuthStore();
    const {
        notifications,
        unreadCount,
        isLoading,
        isConnected,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
        connectWebSocket,
        disconnectWebSocket,
        startPolling,
        stopPolling,
    } = useNotificationsStore();

    const [isOpen, setIsOpen] = useState(false);

    // Connect to WebSocket when user is authenticated
    useEffect(() => {
        if (user?.id) {
            const userId = Number(user.id);
            connectWebSocket(userId);
        }

        return () => {
            disconnectWebSocket();
            stopPolling();
        };
    }, [user?.id, connectWebSocket, disconnectWebSocket, stopPolling]);

    // Fallback to polling if WebSocket connection fails
    useEffect(() => {
        if (!isConnected && user?.id) {
            startPolling();
        }
    }, [isConnected, user?.id, startPolling]);

    // Fetch notifications when dropdown opens
    useEffect(() => {
        if (isOpen) {
            fetchNotifications({ perPage: 10 });
        }
    }, [isOpen, fetchNotifications]);

    const handleNotificationClick = (notification: Notification) => {
        // Mark as read if not already
        if (!notification.is_read) {
            markAsRead(notification.id);
        }

        // Navigate to action URL if present
        if (notification.action_url) {
            navigate(notification.action_url);
            setIsOpen(false);
        }
    };

    const handleMarkAllAsRead = () => {
        markAllAsRead();
    };

    return (
        <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="w-5 h-5" />
                    {unreadCount > 0 && (
                        <Badge
                            className="absolute -top-1 -right-1 w-5 h-5 p-0 flex items-center justify-center bg-red-500 text-white text-xs"
                        >
                            {unreadCount > 99 ? "99+" : unreadCount}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Notificaciones</span>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            className="text-xs text-blue-600 hover:text-blue-800 font-normal"
                            onClick={handleMarkAllAsRead}
                        >
                            Marcar todas como leídas
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {isLoading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="py-8 text-center">
                        <Bell className="w-10 h-10 text-gray-300 mx-auto mb-2" />
                        <p className="text-sm text-gray-500">No tienes notificaciones</p>
                    </div>
                ) : (
                    <ScrollArea className="h-[320px]">
                        <div className="divide-y divide-gray-100">
                            {notifications.map((notification) => (
                                <NotificationItem
                                    key={notification.id}
                                    notification={notification}
                                    onMarkAsRead={markAsRead}
                                    onClick={handleNotificationClick}
                                />
                            ))}
                        </div>
                    </ScrollArea>
                )}

                {notifications.length > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-center justify-center text-blue-600 cursor-pointer"
                            onClick={() => {
                                navigate("/notifications");
                                setIsOpen(false);
                            }}
                        >
                            Ver todas las notificaciones
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default NotificationBell;
