const FALLBACK_TIMEZONE = 'UTC';

function formatLongDate(moment: Date, timeZone: string, locale: string): string | null {
    try {
        return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeZone }).format(moment);
    } catch {
        return null;
    }
}

export function formatPlanEndDate(instant: string, timezone: string, locale: string): string {
    const moment = new Date(instant);

    if (Number.isNaN(moment.getTime())) {
        return instant;
    }

    return formatLongDate(moment, timezone, locale)
        ?? formatLongDate(moment, FALLBACK_TIMEZONE, locale)
        ?? instant;
}
