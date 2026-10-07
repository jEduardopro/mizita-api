import { CheckCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useUnreadNotificationsPreview } from '../queries';
import type { NotificationScope } from '../types';
import { NotificationPopoverItem } from './NotificationPopoverItem';
import { useMarkAsReadAction } from './use-mark-as-read-action';

const PLACEHOLDER_ROWS = [0, 1, 2];

type Props = {
    scope: NotificationScope;
    timezone: string | null;
    onNavigate: () => void;
};

export function NotificationPopoverList({ scope, timezone, onNavigate }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');
    const preview = useUnreadNotificationsPreview(scope);
    const { markAsRead, pendingId } = useMarkAsReadAction();

    if (preview.isPending || timezone === null) {
        return (
            <div role="status" aria-busy="true" className="grid gap-2 p-3">
                {PLACEHOLDER_ROWS.map((row) => (
                    <Skeleton key={row} className="h-12 rounded-lg" />
                ))}
            </div>
        );
    }

    if (preview.isError) {
        return (
            <div className="grid justify-items-start gap-3 px-4 py-5">
                <p className="text-sm text-muted-foreground">{t('notifications.popover.failed')}</p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void preview.refetch()}
                    className="h-11 px-4 md:h-9"
                >
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        );
    }

    const notifications = preview.data.data;

    if (notifications.length === 0) {
        return (
            <div className="grid justify-items-center gap-2 px-4 py-8 text-center">
                <CheckCheck aria-hidden="true" className="size-5 text-muted-foreground" />

                <p className="text-sm font-medium">{t('notifications.popover.empty')}</p>
            </div>
        );
    }

    return (
        <ul className="min-h-0 overflow-y-auto overscroll-contain py-1">
            {notifications.map((notification) => (
                <NotificationPopoverItem
                    key={notification.id}
                    notification={notification}
                    timezone={timezone}
                    recipientName={scope === 'team' ? notification.recipient.name : null}
                    markingAsRead={pendingId === notification.id}
                    onMarkAsRead={markAsRead}
                    onNavigate={onNavigate}
                />
            ))}
        </ul>
    );
}
