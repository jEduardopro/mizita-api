import { useTranslation } from 'react-i18next';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { NotificationFeed } from '@/domains/notifications/components/NotificationFeed';
import { NotificationScopeFilter } from '@/domains/notifications/components/NotificationScopeFilter';
import { NotificationStatusTabs } from '@/domains/notifications/components/NotificationStatusTabs';
import { useNotificationFilters } from '@/domains/notifications/components/use-notification-filters';
import type { NotificationScope } from '@/domains/notifications/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { AdminLayout } from '@/layouts/AdminLayout';

const OWN_SCOPE: NotificationScope = 'mine';

export default function NotificationsIndex() {
    const { t } = useTranslation('admin');
    const { is } = useAuthorization();
    const { status, scope, setStatus, setScope } = useNotificationFilters();
    const timezone = useBusinessTimezone();

    const isOwner = is('owner');
    const visibleScope = isOwner ? scope : OWN_SCOPE;

    return (
        <AdminLayout title={t('notifications.title')}>
            <NotificationStatusTabs
                value={status}
                onValueChange={setStatus}
                filter={
                    isOwner ? <NotificationScopeFilter value={visibleScope} onValueChange={setScope} /> : undefined
                }
            >
                <NotificationFeed scope={visibleScope} status={status} timezone={timezone} />
            </NotificationStatusTabs>
        </AdminLayout>
    );
}
