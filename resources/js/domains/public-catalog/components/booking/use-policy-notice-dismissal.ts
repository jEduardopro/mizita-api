import { useCallback, useState } from 'react';

const STORAGE_KEY_PREFIX = 'mizita:booking-policy-dismissed';
const DISMISSED_VALUE = '1';
const FNV_OFFSET_BASIS = 0x811c9dc5;
const FNV_PRIME = 0x01000193;

function fingerprintOf(text: string): string {
    let hash = FNV_OFFSET_BASIS;

    for (let index = 0; index < text.length; index++) {
        hash ^= text.charCodeAt(index);
        hash = Math.imul(hash, FNV_PRIME);
    }

    return (hash >>> 0).toString(16).padStart(8, '0');
}

function storageKeyFor(businessId: string, message: string): string {
    return `${STORAGE_KEY_PREFIX}:${businessId}:${fingerprintOf(message)}`;
}

function wasDismissed(storageKey: string): boolean {
    try {
        return window.localStorage.getItem(storageKey) === DISMISSED_VALUE;
    } catch {
        return false;
    }
}

function rememberDismissal(storageKey: string): void {
    try {
        window.localStorage.setItem(storageKey, DISMISSED_VALUE);
    } catch {
        return;
    }
}

export function usePolicyNoticeDismissal(businessId: string, message: string) {
    const storageKey = storageKeyFor(businessId, message);
    const [dismissedKey, setDismissedKey] = useState<string | null>(() =>
        wasDismissed(storageKey) ? storageKey : null,
    );

    const dismiss = useCallback(() => {
        rememberDismissal(storageKey);
        setDismissedKey(storageKey);
    }, [storageKey]);

    return { dismissed: dismissedKey === storageKey, dismiss };
}
