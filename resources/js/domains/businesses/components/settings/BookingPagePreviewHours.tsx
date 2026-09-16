import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import type { TimeInterval, WeekdayNumber, WeeklyHours } from '@/domains/businesses/types';
import { formatTimeOfDay } from '@/lib/time';
import { isoWeekdayIn } from '@/lib/timezone';

const MISSING_TIME = '--:--';

const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const satisfies readonly WeekdayNumber[];

const DAY_LABEL_KEYS = {
    1: 'businessSettings.hours.days.monday',
    2: 'businessSettings.hours.days.tuesday',
    3: 'businessSettings.hours.days.wednesday',
    4: 'businessSettings.hours.days.thursday',
    5: 'businessSettings.hours.days.friday',
    6: 'businessSettings.hours.days.saturday',
    7: 'businessSettings.hours.days.sunday',
} as const satisfies Record<WeekdayNumber, string>;

function intervalKey(interval: TimeInterval): string {
    return `${interval.starts_at}-${interval.ends_at}`;
}

function timeLabel(value: string, locale: string): string {
    return value === '' ? MISSING_TIME : formatTimeOfDay(value, locale);
}

function intervalLabel(interval: TimeInterval, locale: string): string {
    return `${timeLabel(interval.starts_at, locale)} – ${timeLabel(interval.ends_at, locale)}`;
}

type Props = {
    hours: WeeklyHours;
    timezone: string;
    todayClassName: string;
};

export function BookingPagePreviewHours({ hours, timezone, todayClassName }: Props) {
    const { t, i18n } = useTranslation('admin');

    const today = isoWeekdayIn(timezone, new Date());
    const isClosedAllWeek = WEEKDAYS.every((weekday) => hours[weekday].length === 0);

    return (
        <div className="grid gap-1.5">
            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {t('businessSettings.preview.hoursTitle')}
            </h3>

            {isClosedAllWeek ? (
                <p className="text-xs text-muted-foreground">
                    {t('businessSettings.preview.hoursEmpty')}
                </p>
            ) : (
                <ul className="grid gap-0.5 text-xs">
                    {WEEKDAYS.map((weekday) => {
                        const intervals = hours[weekday];
                        const isToday = weekday === today;

                        return (
                            <li
                                key={weekday}
                                className={cn(
                                    'flex flex-wrap items-start justify-between gap-x-3 rounded-md px-2 py-1',
                                    isToday && cn(todayClassName, 'font-medium'),
                                )}
                            >
                                <span>
                                    {t(DAY_LABEL_KEYS[weekday])}

                                    {isToday ? (
                                        <span className="sr-only">
                                            {` (${t('businessSettings.preview.today')})`}
                                        </span>
                                    ) : null}
                                </span>

                                {intervals.length === 0 ? (
                                    <span className="ml-auto text-muted-foreground">
                                        {t('businessSettings.hours.closed')}
                                    </span>
                                ) : (
                                    <span className="ml-auto grid justify-items-end">
                                        {intervals.map((interval) => (
                                            <span
                                                key={intervalKey(interval)}
                                                className="whitespace-nowrap"
                                            >
                                                {intervalLabel(interval, i18n.language)}
                                            </span>
                                        ))}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
