import { formatInstantTimeOfDay } from '@/lib/time';

const FALLBACK_TIMEZONE = 'UTC';

const TRAILING_ABBREVIATION_DOT = /\.$/;

const DETAIL_SEPARATOR = ' · ';

const RANGE_SEPARATOR = ' – ';

const MONTH_FORMAT: Intl.DateTimeFormatOptions = { month: 'short' };

const DAY_OF_MONTH_FORMAT: Intl.DateTimeFormatOptions = { day: 'numeric' };

const WEEKDAY_FORMAT: Intl.DateTimeFormatOptions = { weekday: 'short' };

const SHORT_DATE_FORMAT: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };

const LONG_DATE_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
};

function formatter(locale: string, timeZone: string, options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
    try {
        return new Intl.DateTimeFormat(locale, { ...options, timeZone });
    } catch {
        return new Intl.DateTimeFormat(locale, { ...options, timeZone: FALLBACK_TIMEZONE });
    }
}

function formatInstant(
    instant: string,
    timezone: string,
    locale: string,
    options: Intl.DateTimeFormatOptions,
): string {
    return formatter(locale, timezone, options).format(new Date(instant));
}

export function monthAbbreviation(instant: string, timezone: string, locale: string): string {
    return formatInstant(instant, timezone, locale, MONTH_FORMAT).replace(TRAILING_ABBREVIATION_DOT, '');
}

export function dayOfMonth(instant: string, timezone: string, locale: string): string {
    return formatInstant(instant, timezone, locale, DAY_OF_MONTH_FORMAT);
}

export function longDate(instant: string, timezone: string, locale: string): string {
    return formatInstant(instant, timezone, locale, LONG_DATE_FORMAT);
}

export function timeRange(startsAt: string, endsAt: string, timezone: string): string {
    return `${formatInstantTimeOfDay(startsAt, timezone)}${RANGE_SEPARATOR}${formatInstantTimeOfDay(endsAt, timezone)}`;
}

export function weekdayAndTime(instant: string, timezone: string, locale: string): string {
    const weekday = formatInstant(instant, timezone, locale, WEEKDAY_FORMAT).replace(TRAILING_ABBREVIATION_DOT, '');

    return `${weekday}${DETAIL_SEPARATOR}${formatInstantTimeOfDay(instant, timezone)}`;
}

const MILLISECONDS_PER_MINUTE = 60_000;

const MINUTES_PER_HOUR = 60;

const HOURS_PER_DAY = 24;

const MINUTES_PER_DAY = MINUTES_PER_HOUR * HOURS_PER_DAY;

export type ElapsedUnit = 'now' | 'minutes' | 'hours' | 'days';

export type ElapsedTime = {
    unit: ElapsedUnit;
    value: number;
};

export function elapsedSince(instant: string, now: number): ElapsedTime {
    const elapsedMinutes = Math.floor((now - new Date(instant).getTime()) / MILLISECONDS_PER_MINUTE);

    if (! (elapsedMinutes >= 1)) {
        return { unit: 'now', value: 0 };
    }

    if (elapsedMinutes < MINUTES_PER_HOUR) {
        return { unit: 'minutes', value: elapsedMinutes };
    }

    if (elapsedMinutes < MINUTES_PER_DAY) {
        return { unit: 'hours', value: Math.floor(elapsedMinutes / MINUTES_PER_HOUR) };
    }

    return { unit: 'days', value: Math.floor(elapsedMinutes / MINUTES_PER_DAY) };
}

export function dateAndTime(instant: string, timezone: string, locale: string): string {
    const date = formatInstant(instant, timezone, locale, SHORT_DATE_FORMAT);

    return `${date}${DETAIL_SEPARATOR}${formatInstantTimeOfDay(instant, timezone)}`;
}
