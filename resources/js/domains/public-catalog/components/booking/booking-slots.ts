import { formatInstantTimeOfDay } from '@/lib/time';
import { isoDateIn } from '@/lib/timezone';
import type { PublicAvailableDay } from '../../types';

export type BookingSlot = {
    startsAt: string;
    label: string;
};

export type BookingSlotDay = {
    date: string;
    slots: BookingSlot[];
};

export type AvailabilityRange = {
    from: string;
    to: string;
};

type MonthParts = {
    year: number;
    index: number;
};

const ISO_DATE_LENGTH = 'YYYY-MM-DD'.length;

const ISO_MONTH_LENGTH = 'YYYY-MM'.length;

const MONTH_PATTERN = /^(\d{4})-(\d{2})$/;

const FIRST_DAY_SUFFIX = '-01';

const FIRST_DAY_OF_MONTH = 1;

const DAY_BEFORE_FIRST = 0;

const NEXT_MONTH = 1;

function monthParts(month: string): MonthParts | null {
    const parsed = MONTH_PATTERN.exec(month);

    if (parsed === null) {
        return null;
    }

    return { year: Number(parsed[1]), index: Number(parsed[2]) - 1 };
}

function calendarDate(year: number, monthIndex: number, day: number): string {
    return new Date(Date.UTC(year, monthIndex, day)).toISOString().slice(0, ISO_DATE_LENGTH);
}

function byStart(left: BookingSlot, right: BookingSlot): number {
    return Date.parse(left.startsAt) - Date.parse(right.startsAt);
}

export function monthOfIsoDate(date: string): string {
    return date.slice(0, ISO_MONTH_LENGTH);
}

export function firstDayOfMonth(month: string): string {
    return `${month}${FIRST_DAY_SUFFIX}`;
}

export function todayIn(timezone: string): string {
    const now = new Date();

    return isoDateIn(timezone, now) ?? now.toISOString().slice(0, ISO_DATE_LENGTH);
}

export function isUsableTimezone(timezone: string): boolean {
    return isoDateIn(timezone, new Date()) !== null;
}

export function dayOfInstantIn(instant: string | null, timezone: string): string | null {
    if (instant === null) {
        return null;
    }

    return isoDateIn(timezone, new Date(instant));
}

export function availabilityRangeFor(month: string): AvailabilityRange | null {
    const parts = monthParts(month);

    if (parts === null) {
        return null;
    }

    return {
        from: calendarDate(parts.year, parts.index, DAY_BEFORE_FIRST),
        to: calendarDate(parts.year, parts.index + NEXT_MONTH, FIRST_DAY_OF_MONTH),
    };
}

export function bookingSlotDays(
    days: PublicAvailableDay[],
    timezone: string,
    month: string,
): BookingSlotDay[] {
    const byDate = new Map<string, BookingSlot[]>();

    for (const day of days) {
        for (const startsAt of day.starts) {
            const date = isoDateIn(timezone, new Date(startsAt));

            if (date === null || monthOfIsoDate(date) !== month) {
                continue;
            }

            const slots = byDate.get(date) ?? [];

            slots.push({ startsAt, label: formatInstantTimeOfDay(startsAt, timezone) });
            byDate.set(date, slots);
        }
    }

    return [...byDate.entries()]
        .map(([date, slots]) => ({ date, slots: slots.sort(byStart) }))
        .sort((left, right) => left.date.localeCompare(right.date));
}

export function bookableDates(days: BookingSlotDay[]): Set<string> {
    return new Set(days.map((day) => day.date));
}

export function slotsOn(days: BookingSlotDay[], date: string | null): BookingSlot[] {
    return days.find((day) => day.date === date)?.slots ?? [];
}

export function resolveSelectedDate(days: BookingSlotDay[], preferred: string | null): string | null {
    if (preferred !== null && days.some((day) => day.date === preferred)) {
        return preferred;
    }

    return days.length === 0 ? null : days[0].date;
}
