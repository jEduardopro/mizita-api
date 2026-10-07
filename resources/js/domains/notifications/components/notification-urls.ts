import { withReturnTo } from '@/lib/return-to';

export const NOTIFICATIONS_URL = '/notifications';

const SHOW_URL_PREFIX = `${NOTIFICATIONS_URL}/`;

const PATH_SEGMENT_SEPARATOR = '/';

export function notificationShowUrl(id: string): string {
    return `${SHOW_URL_PREFIX}${id}`;
}

export function linkFromNotification(url: string, notificationId: string): string {
    return withReturnTo(url, notificationShowUrl(notificationId));
}

export function isNotificationShowUrl(path: string): boolean {
    if (! path.startsWith(SHOW_URL_PREFIX)) {
        return false;
    }

    const id = path.slice(SHOW_URL_PREFIX.length);

    return id !== '' && ! id.includes(PATH_SEGMENT_SEPARATOR);
}
