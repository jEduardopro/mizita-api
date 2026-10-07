import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useUnreadNotificationCount } from '../queries';
import { NotificationPopoverList } from './NotificationPopoverList';
import { NOTIFICATIONS_URL } from './notification-urls';
import { useBellScope } from './use-bell-scope';

const MAX_DISPLAYED_COUNT = 9;

const CONTENT_CLASS =
    'w-[min(20rem,calc(100vw-1.5rem))] max-h-(--radix-popover-content-available-height) gap-0 rounded-xl p-0 shadow-lg';

type BadgeProps = {
    count: number;
};

function UnreadCountBadge({ count }: BadgeProps) {
    const label = count > MAX_DISPLAYED_COUNT ? `${MAX_DISPLAYED_COUNT}+` : String(count);

    return (
        <span
            aria-hidden="true"
            className="absolute top-1 right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-[0.625rem] leading-none font-semibold text-primary-foreground tabular-nums ring-2 ring-background md:-top-0.5 md:-right-1"
        >
            {label}
        </span>
    );
}

type Props = {
    timezone: string | null;
};

export function NotificationBell({ timezone }: Props) {
    const { t } = useTranslation('admin');
    const [open, setOpen] = useState(false);
    const scope = useBellScope();
    const unreadCount = useUnreadNotificationCount(scope).data ?? 0;

    const triggerLabel =
        unreadCount > 0
            ? t('notifications.bell.labelWithUnread', { count: unreadCount })
            : t('notifications.bell.label');

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={triggerLabel}
                    className="relative size-11 rounded-full md:size-9"
                >
                    <Bell aria-hidden="true" />

                    {unreadCount > 0 ? <UnreadCountBadge count={unreadCount} /> : null}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                aria-label={t('notifications.popover.title')}
                align="end"
                sideOffset={6}
                collisionPadding={12}
                className={CONTENT_CLASS}
            >
                <p className="shrink-0 border-b border-border px-4 py-3 text-sm font-medium">
                    {t('notifications.popover.title')}
                </p>

                <NotificationPopoverList
                    scope={scope}
                    timezone={timezone}
                    onNavigate={() => setOpen(false)}
                />

                <div className="shrink-0 border-t border-border p-1.5">
                    <Button asChild variant="ghost" className="h-11 w-full md:h-9">
                        <Link href={NOTIFICATIONS_URL} onClick={() => setOpen(false)}>
                            {t('notifications.popover.viewAll')}
                        </Link>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
