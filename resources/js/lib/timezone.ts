const FALLBACK_TIMEZONE = 'UTC';

/**
 * The IANA timezone the browser is running in, offered as the default when a
 * business is created. `resolvedOptions()` is guarded because an old or
 * locked-down engine can throw or answer with an empty zone.
 */
export function resolvedTimezone(): string {
    try {
        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return timeZone !== '' ? timeZone : FALLBACK_TIMEZONE;
    } catch {
        return FALLBACK_TIMEZONE;
    }
}
