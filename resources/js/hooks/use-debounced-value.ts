import { useEffect, useState } from 'react';

/**
 * The value, held back until it has stopped changing for `delayMs`.
 *
 * What it is for is the search field that queries the API on every keystroke:
 * debouncing the *value* rather than the handler keeps the input fully
 * controlled, so typing stays instant while the request waits for a pause.
 *
 * The timer is cleared on every change, so only the last value in a burst is
 * ever published.
 */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedValue(value), delayMs);

        return () => window.clearTimeout(timer);
    }, [value, delayMs]);

    return debouncedValue;
}
