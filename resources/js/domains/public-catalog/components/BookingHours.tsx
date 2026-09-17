import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import type { BrandColorClasses, WeekdayNumber } from '@/lib/booking-brand';
import { WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';
import { intervalKeyFor, intervalLabelFor, type BookingDayHours } from './booking-schedule';

type Props = {
    days: BookingDayHours[];
    today: WeekdayNumber | null;
    accent: BrandColorClasses;
};

export function BookingHours({ days, today, accent }: Props) {
    const { t, i18n } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    return (
        <ul className="grid max-w-md gap-0.5 text-sm">
            {days.map((day) => {
                const isToday = day.weekday === today;

                return (
                    <li
                        key={day.weekday}
                        className={cn(
                            'flex items-start justify-between gap-4 rounded-lg px-3 py-2',
                            isToday && cn(accent.surface, 'font-medium'),
                        )}
                    >
                        <span>
                            {tCommon(WEEKDAY_LABEL_KEYS[day.weekday])}

                            {isToday ? (
                                <span className="sr-only">{` (${t('booking.hours.today')})`}</span>
                            ) : null}
                        </span>

                        {day.intervals.length === 0 ? (
                            <span className="text-muted-foreground">
                                {tCommon('hours.closed')}
                            </span>
                        ) : (
                            <span className="grid justify-items-end tabular-nums">
                                {day.intervals.map((interval) => (
                                    <span
                                        key={intervalKeyFor(interval)}
                                        className="whitespace-nowrap"
                                    >
                                        {intervalLabelFor(interval, i18n.language)}
                                    </span>
                                ))}
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
