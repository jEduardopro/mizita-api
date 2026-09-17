export type DatePart = 'day' | 'month' | 'year';

export type DatePartLabels = Record<DatePart, string>;

type DatePattern = {
    order: readonly DatePart[];
    separator: string;
};

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

const REFERENCE_DATE = new Date(Date.UTC(2000, 10, 22));

const FALLBACK_PATTERN: DatePattern = {
    order: ['day', 'month', 'year'],
    separator: '/',
};

function datePart(type: string): DatePart | null {
    if (type === 'day' || type === 'month' || type === 'year') {
        return type;
    }

    return null;
}

function datePattern(locale: string): DatePattern {
    const parts = new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: 'UTC',
    }).formatToParts(REFERENCE_DATE);

    const order = parts
        .map((part) => datePart(part.type))
        .filter((part) => part !== null);

    if (order.length !== FALLBACK_PATTERN.order.length) {
        return FALLBACK_PATTERN;
    }

    return {
        order,
        separator: parts.find((part) => part.type === 'literal')?.value ?? FALLBACK_PATTERN.separator,
    };
}

export function datePlaceholder(locale: string, labels: DatePartLabels): string {
    const { order, separator } = datePattern(locale);

    return order.map((part) => labels[part]).join(separator);
}

export function fullDateFormatter(locale: string): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export function dateFromIso(value: string): Date | null {
    const parsed = ISO_DATE.exec(value);

    if (parsed === null) {
        return null;
    }

    const year = Number(parsed[1]);
    const month = Number(parsed[2]);
    const day = Number(parsed[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

export function isoFromDate(date: Date): string {
    return [
        String(date.getFullYear()).padStart(4, '0'),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

export function formatIsoDate(value: string, locale: string): string {
    const parsed = ISO_DATE.exec(value);

    if (parsed === null) {
        return '';
    }

    const { order, separator } = datePattern(locale);
    const groups: Record<DatePart, string> = {
        year: parsed[1],
        month: parsed[2],
        day: parsed[3],
    };

    return order.map((part) => groups[part]).join(separator);
}
