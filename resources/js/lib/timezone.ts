const FALLBACK_TIMEZONE = 'UTC';

export type IsoWeekday = 1 | 2 | 3 | 4 | 5 | 6 | 7;

const ISO_WEEKDAY_BY_SHORT_NAME: Record<string, IsoWeekday | undefined> = {
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
    Sun: 7,
};

export function isoWeekdayIn(timeZone: string, instant: Date): IsoWeekday | null {
    if (timeZone === '') {
        return null;
    }

    try {
        const shortName = new Intl.DateTimeFormat('en-US', {
            timeZone,
            weekday: 'short',
        }).format(instant);

        return ISO_WEEKDAY_BY_SHORT_NAME[shortName] ?? null;
    } catch {
        return null;
    }
}

export function timeOfDayIn(timeZone: string, instant: Date): string | null {
    if (timeZone === '') {
        return null;
    }

    try {
        return new Intl.DateTimeFormat('en-GB', {
            timeZone,
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).format(instant);
    } catch {
        return null;
    }
}

export function resolvedTimezone(): string {
    try {
        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return timeZone !== '' ? timeZone : FALLBACK_TIMEZONE;
    } catch {
        return FALLBACK_TIMEZONE;
    }
}
