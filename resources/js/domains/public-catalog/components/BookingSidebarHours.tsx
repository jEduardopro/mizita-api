import { cn } from 'cn';
import { ChevronDown, Clock } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { BrandColorClasses, WeekdayNumber } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import { timeOfDayIn } from '@/lib/timezone';
import { WEEKDAY_SHORT_LABEL_KEYS } from '@/lib/weekdays';
import type { PublicOpenState } from '../types';
import { intervalKeyFor, intervalLabelFor, type BookingDayHours } from './booking-schedule';
import { BookingHours } from './BookingHours';

type Props = {
    openState: PublicOpenState;
    days: BookingDayHours[];
    today: WeekdayNumber | null;
    timezone: string;
    accent: BrandColorClasses;
};

export function BookingSidebarHours({ openState, days, today, timezone, accent }: Props) {
    const [expanded, setExpanded] = useState(false);
    const listId = useId();

    return (
        <div className="grid w-full justify-items-center gap-2">
            <button
                type="button"
                aria-expanded={expanded}
                aria-controls={listId}
                onClick={() => setExpanded((current) => ! current)}
                className="flex min-h-11 max-w-full flex-wrap items-center justify-center gap-x-2 cursor-pointer rounded-lg px-3 py-2 text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <Clock aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />

                <HoursSummary openState={openState} days={days} today={today} timezone={timezone} />

                <ChevronDown
                    aria-hidden="true"
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform motion-reduce:transition-none',
                        expanded && 'rotate-180',
                    )}
                />
            </button>

            <div id={listId} hidden={! expanded} className="w-full text-left">
                <BookingHours days={days} today={today} accent={accent} className="max-w-none" />
            </div>
        </div>
    );
}

type HoursSummaryProps = Omit<Props, 'accent'>;

function HoursSummary({ openState, days, today, timezone }: HoursSummaryProps) {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    if (openState.open) {
        const todayIntervals = days.find((day) => day.weekday === today)?.intervals ?? [];

        return (
            <>
                <span className="text-muted-foreground">{t('booking.hours.today')}</span>

                {todayIntervals.map((interval) => (
                    <span key={intervalKeyFor(interval)} className="whitespace-nowrap tabular-nums">
                        {intervalLabelFor(interval)}
                    </span>
                ))}
            </>
        );
    }

    const closed = <span>{tCommon('hours.closed')}</span>;

    if (openState.opens_at === null) {
        return closed;
    }

    const time = formatTimeOfDay(openState.opens_at);
    const opensOn = openState.opens_on_weekday;
    const opensLaterToday = opensOn === today && isLaterToday(openState.opens_at, timezone);

    const opensLabel = opensOn === null || opensLaterToday
        ? t('booking.hours.opensAt', { time })
        : t('booking.hours.opensOn', { time, weekday: tCommon(WEEKDAY_SHORT_LABEL_KEYS[opensOn]) });

    return (
        <>
            {closed}

            <span aria-hidden="true" className="text-muted-foreground">·</span>

            <span className="whitespace-nowrap tabular-nums">{opensLabel}</span>
        </>
    );
}

function isLaterToday(timeOfDay: string, timezone: string): boolean {
    const currentTimeOfDay = timeOfDayIn(timezone, new Date());

    return currentTimeOfDay !== null && timeOfDay > currentTimeOfDay;
}
