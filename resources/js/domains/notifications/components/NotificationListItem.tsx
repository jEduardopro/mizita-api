import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { StaffNotification } from '../types';
import { NotificationAppointmentTime } from './NotificationAppointmentTime';
import { NotificationDateLeaf } from './NotificationDateLeaf';
import { NotificationReceivedAgo } from './NotificationReceivedAgo';
import { appointmentStartOf } from './notification-appointment-start';
import { notificationShowUrl } from './notification-urls';
import { useNotificationMessage } from './use-notification-message';

type Props = {
    notification: StaffNotification;
    timezone: string;
    recipientName: string | null;
    markingAsRead: boolean;
    onMarkAsRead: (id: string) => void;
};

export function NotificationListItem({
    notification,
    timezone,
    recipientName,
    markingAsRead,
    onMarkAsRead,
}: Props) {
    const { t } = useTranslation('admin');
    const message = useNotificationMessage(notification);
    const appointmentStartsAt = appointmentStartOf(notification);
    const unread = notification.read_at === null;

    return (
        <li
            className={cn(
                'relative flex flex-col gap-3 rounded-xl border border-border py-3 pr-3 pl-4 hover:bg-muted/60 has-[a:focus-visible]:ring-3 has-[a:focus-visible]:ring-ring/50 motion-safe:transition-colors sm:flex-row sm:items-center',
                unread ? 'bg-card' : 'bg-transparent',
            )}
        >
            <div className="flex min-w-0 flex-1 items-start gap-3">
                <NotificationDateLeaf
                    startsAt={appointmentStartsAt}
                    timezone={timezone}
                    unread={unread}
                />

                <div className="grid min-w-0 flex-1 gap-0.5 py-0.5">
                    <div className="flex items-start gap-2">
                        <Link
                            href={notificationShowUrl(notification.id)}
                            className={cn(
                                'min-w-0 flex-1 text-sm text-pretty outline-none after:absolute after:inset-0 after:rounded-xl',
                                unread ? 'font-medium' : 'text-muted-foreground',
                            )}
                        >
                            {unread ? <span className="sr-only">{`${t('notifications.unread')}: `}</span> : null}
                            {message}
                        </Link>

                        <NotificationReceivedAgo createdAt={notification.created_at} timezone={timezone} />
                    </div>

                    {appointmentStartsAt === null ? null : (
                        <NotificationAppointmentTime startsAt={appointmentStartsAt} timezone={timezone} />
                    )}

                    {recipientName === null ? null : (
                        <p className="truncate text-xs text-muted-foreground">
                            {t('notifications.recipient', { name: recipientName })}
                        </p>
                    )}
                </div>
            </div>

            {notification.can_mark_as_read ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={markingAsRead}
                    onClick={() => onMarkAsRead(notification.id)}
                    className="relative z-10 h-11 self-start px-4 sm:self-center md:h-9"
                >
                    <Check aria-hidden="true" />
                    {t('notifications.actions.markAsRead')}
                </Button>
            ) : null}
        </li>
    );
}
