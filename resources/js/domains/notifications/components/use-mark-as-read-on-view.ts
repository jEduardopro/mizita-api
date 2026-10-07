import { useEffect, useRef } from 'react';
import { useMarkNotificationAsRead } from '../queries';
import type { StaffNotification } from '../types';

export function useMarkAsReadOnView(notification: StaffNotification | undefined): void {
    const { mutate } = useMarkNotificationAsRead();
    const markedId = useRef<string | null>(null);

    const id = notification?.id;
    const canMarkAsRead = notification?.can_mark_as_read ?? false;

    useEffect(() => {
        if (id === undefined || ! canMarkAsRead || markedId.current === id) {
            return;
        }

        markedId.current = id;
        mutate(id);
    }, [id, canMarkAsRead, mutate]);
}
