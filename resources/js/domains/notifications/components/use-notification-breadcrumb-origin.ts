import { useTranslation } from 'react-i18next';
import type { BreadcrumbOrigin } from '@/hooks/use-origin-breadcrumbs';
import { isNotificationShowUrl, NOTIFICATIONS_URL } from './notification-urls';

export function useNotificationBreadcrumbOrigin(): BreadcrumbOrigin {
    const { t } = useTranslation('admin');

    return {
        matches: isNotificationShowUrl,
        trail: (path) => [
            { label: t('notifications.title'), href: NOTIFICATIONS_URL },
            { label: t('notifications.show.title'), href: path },
        ],
    };
}
