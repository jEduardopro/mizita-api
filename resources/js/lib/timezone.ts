const FALLBACK_TIMEZONE = 'UTC';

export function resolvedTimezone(): string {
    try {
        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return timeZone !== '' ? timeZone : FALLBACK_TIMEZONE;
    } catch {
        return FALLBACK_TIMEZONE;
    }
}
