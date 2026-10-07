import { api } from '@/lib/api';
import type { Paginated } from '@/types/api';
import type {
    NotificationListParams,
    NotificationScope,
    StaffNotification,
    UnreadNotificationCount,
} from './types';

export async function listNotifications(
    params: NotificationListParams,
    signal?: AbortSignal,
): Promise<Paginated<StaffNotification>> {
    const { data } = await api.get<Paginated<StaffNotification>>('/notifications', {
        params,
        signal,
    });

    return data;
}

export async function getUnreadNotificationCount(
    scope: NotificationScope,
    signal?: AbortSignal,
): Promise<number> {
    const { data } = await api.get<{ data: UnreadNotificationCount }>('/notifications/unread-count', {
        params: { scope },
        signal,
    });

    return data.data.count;
}

export async function getNotification(id: string, signal?: AbortSignal): Promise<StaffNotification> {
    const { data } = await api.get<{ data: StaffNotification }>(`/notifications/${id}`, { signal });

    return data.data;
}

export async function markNotificationAsRead(id: string): Promise<StaffNotification> {
    const { data } = await api.post<{ data: StaffNotification }>(`/notifications/${id}/read`);

    return data.data;
}
