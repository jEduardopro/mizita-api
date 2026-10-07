import { LoaderCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTableError } from '@/components/shared/data-table/DataTableError';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useIntersection } from '@/hooks/use-intersection';
import { useInfiniteNotifications } from '../queries';
import type { NotificationScope, NotificationStatus } from '../types';
import { NotificationListItem } from './NotificationListItem';
import { NotificationsEmptyState } from './NotificationsEmptyState';
import { useMarkAsReadAction } from './use-mark-as-read-action';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

type Props = {
    scope: NotificationScope;
    status: NotificationStatus;
    timezone: string | null;
};

export function NotificationFeed({ scope, status, timezone }: Props) {
    const { t } = useTranslation('common');
    const feed = useInfiniteNotifications({ scope, status });
    const { markAsRead, pendingId } = useMarkAsReadAction();

    const { fetchNextPage, hasNextPage, isFetchingNextPage } = feed;

    const loadMore = useCallback(() => void fetchNextPage(), [fetchNextPage]);

    const sentinelRef = useIntersection({
        enabled: hasNextPage && ! isFetchingNextPage,
        onIntersect: loadMore,
    });

    if (feed.isPending || timezone === null) {
        return (
            <div role="status" aria-busy="true" className="grid gap-3">
                {PLACEHOLDER_ROWS.map((row) => (
                    <Skeleton key={row} className="h-[4.5rem] rounded-xl" />
                ))}
            </div>
        );
    }

    if (feed.isError) {
        return <DataTableError onRetry={() => void feed.refetch()} />;
    }

    const notifications = feed.data.pages.flatMap((page) => page.data);

    if (notifications.length === 0) {
        return <NotificationsEmptyState status={status} />;
    }

    return (
        <div className="grid gap-4">
            <ul className="grid gap-3">
                {notifications.map((notification) => (
                    <NotificationListItem
                        key={notification.id}
                        notification={notification}
                        timezone={timezone}
                        recipientName={scope === 'team' ? notification.recipient.name : null}
                        markingAsRead={pendingId === notification.id}
                        onMarkAsRead={markAsRead}
                    />
                ))}
            </ul>

            <div ref={sentinelRef} aria-hidden="true" />

            {hasNextPage ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={isFetchingNextPage}
                    onClick={loadMore}
                    className="h-11 justify-self-center px-5 md:h-9"
                >
                    {isFetchingNextPage ? (
                        <>
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                            {t('actions.loadingMore')}
                        </>
                    ) : (
                        t('actions.loadMore')
                    )}
                </Button>
            ) : null}
        </div>
    );
}
