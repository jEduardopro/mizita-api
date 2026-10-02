import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const MINUTE_MS = 60_000;

function millisecondsUntilNextMinute(remainingMs: number): number {
    return remainingMs % MINUTE_MS || MINUTE_MS;
}

export function useImpersonationCountdown(expiresAt: string): number {
    const expiresAtMs = Date.parse(expiresAt);
    const [now, setNow] = useState(() => Date.now());
    const remainingMs = Math.max(0, expiresAtMs - now);

    useEffect(() => {
        if (remainingMs === 0) {
            router.reload();
        }

        const timer = window.setTimeout(
            () => setNow(Date.now()),
            millisecondsUntilNextMinute(remainingMs),
        );

        return () => window.clearTimeout(timer);
    }, [now, remainingMs]);

    return Math.ceil(remainingMs / MINUTE_MS);
}
