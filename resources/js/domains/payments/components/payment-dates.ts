const FALLBACK_TIMEZONE = 'UTC';

const MONTH_ABBREVIATION_DOT = /\.$/;

function dateParts(locale: string, timeZone: string, moment: Date): Intl.DateTimeFormatPart[] | null {
    try {
        return new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone,
        }).formatToParts(moment);
    } catch {
        return null;
    }
}

export function formatTransactionDate(instant: string, timezone: string, locale: string): string {
    const moment = new Date(instant);

    if (Number.isNaN(moment.getTime())) {
        return instant;
    }

    const parts = dateParts(locale, timezone, moment) ?? dateParts(locale, FALLBACK_TIMEZONE, moment);

    if (parts === null) {
        return instant;
    }

    const valueOf = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? '';

    const day = valueOf('day');
    const month = valueOf('month').replace(MONTH_ABBREVIATION_DOT, '');
    const year = valueOf('year');

    return `${day} ${month} ${year}`;
}
