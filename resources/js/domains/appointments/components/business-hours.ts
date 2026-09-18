import type { BackgroundEvent } from '@schedule-x/calendar';
import 'temporal-polyfill/global';
import { WEEKDAYS, type TimeInterval, type WeekdayNumber } from '@/lib/booking-brand';

export type BusinessScheduleRule = TimeInterval & { weekday: WeekdayNumber };

const WINDOW_DAYS_PAST = 180;

const WINDOW_DAYS_FUTURE = 180;

const FULL_DAY_START = '00:00';

const FULL_DAY_END = '24:00';

const CLOSED_STYLE = { backgroundColor: 'color-mix(in oklab, var(--color-foreground) 11%, var(--color-background))' };

function closedRangesFor(intervals: TimeInterval[]): TimeInterval[] {
    if (intervals.length === 0) {
        return [{ starts_at: FULL_DAY_START, ends_at: FULL_DAY_END }];
    }

    const sorted = [...intervals].sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    const ranges: TimeInterval[] = [];
    let cursor = FULL_DAY_START;

    for (const interval of sorted) {
        if (interval.starts_at > cursor) {
            ranges.push({ starts_at: cursor, ends_at: interval.starts_at });
        }

        if (interval.ends_at > cursor) {
            cursor = interval.ends_at;
        }
    }

    if (cursor < FULL_DAY_END) {
        ranges.push({ starts_at: cursor, ends_at: FULL_DAY_END });
    }

    return ranges;
}

function zonedTimeOn(date: Temporal.PlainDate, time: string, timezone: string): Temporal.ZonedDateTime {
    const [hourText, minuteText] = time.split(':');
    const hour = Number(hourText);
    const minute = Number(minuteText);

    if (hour >= 24) {
        return date.add({ days: 1 }).toZonedDateTime({ timeZone: timezone, plainTime: { hour: 0, minute } });
    }

    return date.toZonedDateTime({ timeZone: timezone, plainTime: { hour, minute } });
}

export function businessHoursBackgroundEvents(
    schedule: BusinessScheduleRule[],
    timezone: string,
): BackgroundEvent[] {
    const closedRangesByWeekday = new Map<WeekdayNumber, TimeInterval[]>(
        WEEKDAYS.map((weekday) => [
            weekday,
            closedRangesFor(schedule.filter((rule) => rule.weekday === weekday)),
        ]),
    );

    const events: BackgroundEvent[] = [];
    const today = Temporal.Now.plainDateISO(timezone);
    const firstDay = today.subtract({ days: WINDOW_DAYS_PAST });
    const windowLength = WINDOW_DAYS_PAST + WINDOW_DAYS_FUTURE;

    for (let offset = 0; offset < windowLength; offset += 1) {
        const date = firstDay.add({ days: offset });
        const weekday = date.dayOfWeek as WeekdayNumber;
        const ranges = closedRangesByWeekday.get(weekday) ?? [];

        for (const range of ranges) {
            events.push({
                start: zonedTimeOn(date, range.starts_at, timezone),
                end: zonedTimeOn(date, range.ends_at, timezone),
                style: CLOSED_STYLE,
            });
        }
    }

    return events;
}
