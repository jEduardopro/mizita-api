import { Bell, CalendarPlus, RefreshCw, UserPlus, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export const ACTIVITY_SCREEN_WIDTH = 176;
export const ACTIVITY_SCREEN_HEIGHT = 308;

const events = [
    {
        id: 'booked',
        Icon: CalendarPlus,
        copy: 'heroCollage.activity.items.booked.copy',
        time: 'heroCollage.activity.items.booked.time',
    },
    {
        id: 'rescheduled',
        Icon: RefreshCw,
        copy: 'heroCollage.activity.items.rescheduled.copy',
        time: 'heroCollage.activity.items.rescheduled.time',
    },
    {
        id: 'reminder',
        Icon: Bell,
        copy: 'heroCollage.activity.items.reminder.copy',
        time: 'heroCollage.activity.items.reminder.time',
    },
    {
        id: 'customer',
        Icon: UserPlus,
        copy: 'heroCollage.activity.items.customer.copy',
        time: 'heroCollage.activity.items.customer.time',
    },
    {
        id: 'cancelled',
        Icon: X,
        copy: 'heroCollage.activity.items.cancelled.copy',
        time: 'heroCollage.activity.items.cancelled.time',
    },
] as const;

export function ActivityScreen() {
    const { t } = useTranslation('public');

    return (
        <div className="flex h-full flex-col px-3 py-3">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[0.5625rem] leading-none font-medium tracking-[0.14em] text-muted-foreground uppercase">
                    {t('heroCollage.activity.title')}
                </span>
                <span className="size-1.5 rounded-[2px] bg-primary/70" />
            </div>

            <ul className="mt-3 space-y-2.5">
                {events.map(({ id, Icon, copy, time }) => (
                    <li key={id} className="flex gap-2">
                        <span className="mt-px flex size-6 shrink-0 items-center justify-center rounded-md bg-brand-50 text-primary dark:bg-brand-950/70">
                            <Icon className="size-3" />
                        </span>

                        <span className="min-w-0 flex-1">
                            <span className="block text-[0.625rem] leading-snug text-foreground">
                                {t(copy)}
                            </span>
                            <span className="mt-0.5 block text-[0.5625rem] leading-none text-muted-foreground">
                                {t(time)}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
