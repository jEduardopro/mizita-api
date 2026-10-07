import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { StaffNotification } from '../types';
import { NotificationAppointmentTime } from './NotificationAppointmentTime';
import { NotificationDateLeaf } from './NotificationDateLeaf';
import { NotificationReceivedAgo } from './NotificationReceivedAgo';
import { notificationShowUrl } from './notification-urls';
import { useNotificationMessage } from './use-notification-message';

type Props = {
    notification: StaffNotification;
    timezone: string;
    recipientName: string | null;
    markingAsRead: boolean;
    onMarkAsRead: (id: string) => void;
    onNavigate: () => void;
};

export function NotificationPopoverItem({
    notification,
    timezone,
    recipientName,
    markingAsRead,
    onMarkAsRead,
    onNavigate,
}: Props) {
    const { t } = useTranslation('admin');
    const message = useNotificationMessage(notification);
    const { appointment } = notification;

    return (
        <li className="relative flex items-start gap-3 px-3 py-2.5 hover:bg-muted has-[a:focus-visible]:bg-muted motion-safe:transition-colors">
            <NotificationDateLeaf startsAt={appointment?.starts_at ?? null} timezone={timezone} />

            <div className="grid min-w-0 flex-1 gap-0.5 py-0.5">
                <div className="flex items-start gap-2">
                    <Link
                        href={notificationShowUrl(notification.id)}
                        onClick={onNavigate}
                        className="min-w-0 flex-1 text-sm font-medium text-pretty outline-none after:absolute after:inset-0 focus-visible:underline"
                    >
                        {message}
                    </Link>

                    <NotificationReceivedAgo createdAt={notification.created_at} timezone={timezone} />
                </div>

                {appointment === null ? null : (
                    <NotificationAppointmentTime startsAt={appointment.starts_at} timezone={timezone} />
                )}

                {recipientName === null ? null : (
                    <p className="truncate text-xs text-muted-foreground">
                        {t('notifications.recipient', { name: recipientName })}
                    </p>
                )}
            </div>

            {notification.can_mark_as_read ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('notifications.actions.markAsRead')}
                    title={t('notifications.actions.markAsRead')}
                    disabled={markingAsRead}
                    onClick={() => onMarkAsRead(notification.id)}
                    className="relative z-10 -my-1 size-11 shrink-0 rounded-full text-muted-foreground md:size-9"
                >
                    <Check aria-hidden="true" />
                </Button>
            ) : null}
        </li>
    );
}
