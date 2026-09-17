const TIME_OF_DAY_PATTERN = /^(\d{2}):(\d{2})$/;

const REFERENCE_DAY = Date.UTC(1970, 0, 1);

const MINUTE_IN_MS = 60_000;

const HOUR_IN_MS = 60 * MINUTE_IN_MS;

const ISO_DATE_LENGTH = 'YYYY-MM-DD'.length;

export function todayAsIsoDate(): string {
    const today = new Date();

    return new Date(today.getTime() - today.getTimezoneOffset() * MINUTE_IN_MS)
        .toISOString()
        .slice(0, ISO_DATE_LENGTH);
}

export function formatTimeOfDay(value: string, locale: string): string {
    const parts = TIME_OF_DAY_PATTERN.exec(value);

    if (parts === null) {
        return value;
    }

    const hours = Number(parts[1]);
    const minutes = Number(parts[2]);

    if (hours > 23 || minutes > 59) {
        return value;
    }

    try {
        return new Intl.DateTimeFormat(locale, {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: 'UTC',
        }).format(new Date(REFERENCE_DAY + hours * HOUR_IN_MS + minutes * MINUTE_IN_MS));
    } catch {
        return value;
    }
}
