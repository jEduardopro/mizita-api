const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

const PERCENT_SCALE = 100;

const PERCENT_FRACTION_DIGITS = 1;

const RANGE_FALLBACK_SEPARATOR = ' – ';

const CALENDAR_TIMEZONE = 'UTC';

function calendarDate(value: string): Date | null {
    const parsed = ISO_DATE.exec(value);

    if (parsed === null) {
        return null;
    }

    return new Date(Date.UTC(Number(parsed[1]), Number(parsed[2]) - 1, Number(parsed[3])));
}

export function formatCount(value: number, locale: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

export function formatSharePercent(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'percent',
        maximumFractionDigits: PERCENT_FRACTION_DIGITS,
    }).format(value / PERCENT_SCALE);
}

export function formatChangePercent(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'percent',
        maximumFractionDigits: PERCENT_FRACTION_DIGITS,
        signDisplay: 'exceptZero',
    }).format(value / PERCENT_SCALE);
}

export function formatDateRange(from: string, to: string, locale: string): string {
    const start = calendarDate(from);
    const end = calendarDate(to);

    if (start === null || end === null) {
        return `${from}${RANGE_FALLBACK_SEPARATOR}${to}`;
    }

    const spansYears = start.getUTCFullYear() !== end.getUTCFullYear();

    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: 'short',
        year: spansYears ? 'numeric' : undefined,
        timeZone: CALENDAR_TIMEZONE,
    }).formatRange(start, end);
}

export function formatShortWeekday(date: string, locale: string): string {
    const day = calendarDate(date);

    if (day === null) {
        return date;
    }

    return new Intl.DateTimeFormat(locale, { weekday: 'short', timeZone: CALENDAR_TIMEZONE }).format(day);
}

export function formatLongDate(date: string, locale: string): string {
    const day = calendarDate(date);

    if (day === null) {
        return date;
    }

    return new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: CALENDAR_TIMEZONE,
    }).format(day);
}
