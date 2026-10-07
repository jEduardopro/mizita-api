import { cn } from 'cn';
import { Bell } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { dayOfMonth, monthAbbreviation } from './notification-dates';

type LeafSize = 'default' | 'lg';

type LeafClasses = {
    leaf: string;
    month: string;
    day: string;
};

const LEAF_CLASSES: Record<LeafSize, LeafClasses> = {
    default: {
        leaf: 'w-11',
        month: 'py-0.5 text-[0.625rem]',
        day: 'h-7 text-base',
    },
    lg: {
        leaf: 'w-16',
        month: 'py-1 text-xs',
        day: 'h-11 text-2xl',
    },
};

type Props = {
    startsAt: string | null;
    timezone: string;
    unread?: boolean;
    size?: LeafSize;
};

export function NotificationDateLeaf({ startsAt, timezone, unread = false, size = 'default' }: Props) {
    const { i18n } = useTranslation();
    const classes = LEAF_CLASSES[size];

    return (
        <span aria-hidden="true" className={cn('relative shrink-0 self-start', classes.leaf)}>
            <span className="grid overflow-hidden rounded-lg border border-border bg-background text-center">
                {startsAt === null ? (
                    <span className="flex aspect-square items-center justify-center text-muted-foreground">
                        <Bell className="size-4" />
                    </span>
                ) : (
                    <>
                        <span
                            className={cn(
                                'bg-primary/10 leading-none font-semibold tracking-wide text-primary uppercase',
                                classes.month,
                            )}
                        >
                            {monthAbbreviation(startsAt, timezone, i18n.language)}
                        </span>

                        <span
                            className={cn(
                                'flex items-center justify-center leading-none font-semibold tabular-nums',
                                classes.day,
                            )}
                        >
                            {dayOfMonth(startsAt, timezone, i18n.language)}
                        </span>
                    </>
                )}
            </span>

            {unread ? (
                <span className="absolute -top-1 -right-1 size-2.5 rounded-full bg-primary ring-2 ring-background" />
            ) : null}
        </span>
    );
}
