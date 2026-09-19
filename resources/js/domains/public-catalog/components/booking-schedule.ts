import { WEEKDAYS, type TimeInterval, type WeekdayNumber } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import type { PublicScheduleEntry } from '../types';

const DAYS_IN_WEEK = WEEKDAYS.length;

export type BookingDayHours = {
    weekday: WeekdayNumber;
    intervals: TimeInterval[];
};

export type BookingOpenState =
    | { status: 'open'; closesAt: string }
    | { status: 'closed'; opensWeekday: WeekdayNumber; opensAt: string }
    | { status: 'unknown' };

export function weeklyHoursFrom(entries: PublicScheduleEntry[]): BookingDayHours[] {
    return WEEKDAYS.map((weekday) => ({
        weekday,
        intervals: entries
            .filter((entry) => entry.weekday === weekday)
            .map((entry) => ({ starts_at: entry.starts_at, ends_at: entry.ends_at }))
            .sort((left, right) => left.starts_at.localeCompare(right.starts_at)),
    }));
}

export function intervalKeyFor(interval: TimeInterval): string {
    return `${interval.starts_at}-${interval.ends_at}`;
}

export function intervalLabelFor(interval: TimeInterval): string {
    const opens = formatTimeOfDay(interval.starts_at);
    const closes = formatTimeOfDay(interval.ends_at);

    return `${opens} – ${closes}`;
}

function intervalsOn(days: BookingDayHours[], weekday: WeekdayNumber): TimeInterval[] {
    return days.find((day) => day.weekday === weekday)?.intervals ?? [];
}

function weekdayAfter(weekday: WeekdayNumber, offset: number): WeekdayNumber {
    return WEEKDAYS[(weekday - 1 + offset) % DAYS_IN_WEEK];
}

export function resolveOpenState(
    days: BookingDayHours[],
    weekday: WeekdayNumber | null,
    timeOfDay: string | null,
): BookingOpenState {
    if (weekday === null || timeOfDay === null) {
        return { status: 'unknown' };
    }

    const current = intervalsOn(days, weekday).find(
        (interval) => interval.starts_at <= timeOfDay && timeOfDay < interval.ends_at,
    );

    if (current !== undefined) {
        return { status: 'open', closesAt: current.ends_at };
    }

    for (let offset = 0; offset < DAYS_IN_WEEK; offset += 1) {
        const day = weekdayAfter(weekday, offset);
        const next = intervalsOn(days, day).find(
            (interval) => offset > 0 || interval.starts_at > timeOfDay,
        );

        if (next !== undefined) {
            return { status: 'closed', opensWeekday: day, opensAt: next.starts_at };
        }
    }

    return { status: 'unknown' };
}
