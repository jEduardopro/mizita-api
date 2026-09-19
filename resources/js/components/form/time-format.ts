import { foldForSearch } from '@/lib/text';
import { formatTimeOfDay } from '@/lib/time';

export type TimeOption = {
    value: string;
    label: string;
    minutes: number;
};

export const DEFAULT_TIME_STEP_MINUTES = 5;

const MINUTES_IN_HOUR = 60;

const HOURS_IN_DAY = 24;

const HOURS_IN_HALF_DAY = 12;

const MINUTES_IN_DAY = HOURS_IN_DAY * MINUTES_IN_HOUR;

const MINIMUM_STEP_MINUTES = 1;

const CLOCK_DIGITS = 2;

const AM_TOKEN = 'a';

const TIME_OF_DAY = /^(\d{1,2}):(\d{2})$/;

const MERIDIEM_SUFFIX = /\s*([ap])\.?\s*m?\.?\s*$/i;

const SEPARATED_QUERY = /^(\d{1,2})\s*[:.,]\s*(\d{1,2})$/;

const COMPACT_QUERY = /^(\d{1,4})$/;

type ClockReading = {
    hours: number;
    minutes: number;
};

function padClock(value: number): string {
    return String(value).padStart(CLOCK_DIGITS, '0');
}

function readCompactQuery(digits: string): ClockReading {
    if (digits.length <= CLOCK_DIGITS) {
        return { hours: Number(digits), minutes: 0 };
    }

    const boundary = digits.length - CLOCK_DIGITS;

    return { hours: Number(digits.slice(0, boundary)), minutes: Number(digits.slice(boundary)) };
}

function readClockQuery(text: string): ClockReading | null {
    const separated = SEPARATED_QUERY.exec(text);

    if (separated !== null) {
        return { hours: Number(separated[1]), minutes: Number(separated[2]) };
    }

    const compact = COMPACT_QUERY.exec(text);

    if (compact === null) {
        return null;
    }

    return readCompactQuery(compact[1]);
}

function parseTimeQuery(query: string): number | null {
    const text = query.trim();
    const meridiem = MERIDIEM_SUFFIX.exec(text);
    const reading = readClockQuery(text.replace(MERIDIEM_SUFFIX, '').trim());

    if (reading === null || reading.minutes >= MINUTES_IN_HOUR) {
        return null;
    }

    if (meridiem === null) {
        return reading.hours >= HOURS_IN_DAY ? null : reading.hours * MINUTES_IN_HOUR + reading.minutes;
    }

    if (reading.hours < 1 || reading.hours > HOURS_IN_HALF_DAY) {
        return null;
    }

    const hoursOnClock = reading.hours % HOURS_IN_HALF_DAY;
    const hours =
        meridiem[1].toLowerCase() === AM_TOKEN ? hoursOnClock : hoursOnClock + HOURS_IN_HALF_DAY;

    return hours * MINUTES_IN_HOUR + reading.minutes;
}

export function minutesFromTime(value: string): number | null {
    const parts = TIME_OF_DAY.exec(value);

    if (parts === null) {
        return null;
    }

    const hours = Number(parts[1]);
    const minutes = Number(parts[2]);

    if (hours >= HOURS_IN_DAY || minutes >= MINUTES_IN_HOUR) {
        return null;
    }

    return hours * MINUTES_IN_HOUR + minutes;
}

export function timeFromMinutes(minutes: number): string {
    return `${padClock(Math.floor(minutes / MINUTES_IN_HOUR))}:${padClock(minutes % MINUTES_IN_HOUR)}`;
}

function formatMinutes(minutes: number): string {
    return formatTimeOfDay(timeFromMinutes(minutes));
}

export function formatTime(value: string): string {
    const minutes = minutesFromTime(value);

    if (minutes === null) {
        return '';
    }

    return formatMinutes(minutes);
}

export function buildTimeOptions(fromMinutes: number, stepMinutes: number): readonly TimeOption[] {
    const step = Math.max(Math.trunc(stepMinutes), MINIMUM_STEP_MINUTES);
    const options: TimeOption[] = [];

    for (let minutes = Math.max(fromMinutes, 0); minutes < MINUTES_IN_DAY; minutes += step) {
        options.push({ value: timeFromMinutes(minutes), label: formatMinutes(minutes), minutes });
    }

    return options;
}

export function timeOptionIndexFrom(options: readonly TimeOption[], minutes: number): number {
    return options.findIndex((option) => option.minutes >= minutes);
}

export function filterTimeOptions(
    options: readonly TimeOption[],
    query: string,
): readonly TimeOption[] {
    const search = query.trim();

    if (search === '') {
        return options;
    }

    const minutes = parseTimeQuery(search);

    if (minutes !== null) {
        const index = timeOptionIndexFrom(options, minutes);

        return index < 0 ? [] : options.slice(index);
    }

    const needle = foldForSearch(search);

    return options.filter((option) => foldForSearch(option.label).includes(needle));
}
