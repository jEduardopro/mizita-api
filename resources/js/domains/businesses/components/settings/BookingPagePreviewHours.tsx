import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { WEEKDAYS, type TimeInterval, type WeeklyHours } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import { isoWeekdayIn } from '@/lib/timezone';
import { WEEKDAY_LABEL_KEYS } from '@/lib/weekdays';

const MISSING_TIME = '--:--';

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
    const { t: tCommon } = useTranslation('common');

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
                                    {tCommon(WEEKDAY_LABEL_KEYS[weekday])}

                                    {isToday ? (
                                        <span className="sr-only">
                                            {` (${t('businessSettings.preview.today')})`}
                                        </span>
                                    ) : null}
                                </span>

                                {intervals.length === 0 ? (
                                    <span className="ml-auto text-muted-foreground">
                                        {tCommon('hours.closed')}
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
