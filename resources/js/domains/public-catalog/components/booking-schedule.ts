import { WEEKDAYS, type TimeInterval, type WeekdayNumber } from '@/lib/booking-brand';
import { formatTimeOfDay } from '@/lib/time';
import type { PublicScheduleEntry } from '../types';

export type BookingDayHours = {
    weekday: WeekdayNumber;
    intervals: TimeInterval[];
};

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
