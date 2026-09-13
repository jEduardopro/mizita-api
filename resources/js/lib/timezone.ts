/**
 * The zone to fall back to when the browser cannot name its own.
 *
 * It is a real IANA identifier rather than an empty string, because the value is
 * sent to the API and a business without a usable timezone would compute every
 * slot against nothing.
 */
const FALLBACK_TIMEZONE = 'UTC';

/**
 * The IANA timezone the browser is running in, e.g. `America/Mexico_City`.
 *
 * A business's timezone is the only source of local time for its agenda, so this
 * is offered as the default when one is being created — it is a suggestion the
 * person can change, never an assumption made on their behalf.
 *
 * `resolvedOptions()` is guarded because an old or locked-down engine can throw
 * or answer with an empty zone, and a screen must not fail to render over it.
 */
export function resolvedTimezone(): string {
    try {
        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return timeZone !== '' ? timeZone : FALLBACK_TIMEZONE;
    } catch {
        return FALLBACK_TIMEZONE;
    }
}
