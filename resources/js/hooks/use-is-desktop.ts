import { useSyncExternalStore } from 'react';

const DESKTOP_QUERY = '(min-width: 48rem)';

let desktop: MediaQueryList | null = null;

function desktopQuery(): MediaQueryList {
    desktop ??= window.matchMedia(DESKTOP_QUERY);

    return desktop;
}

function subscribeToDesktop(notify: () => void): () => void {
    const query = desktopQuery();

    query.addEventListener('change', notify);

    return () => query.removeEventListener('change', notify);
}

function desktopSnapshot(): boolean {
    return desktopQuery().matches;
}

function serverSnapshot(): boolean {
    return false;
}

export function useIsDesktop(): boolean {
    return useSyncExternalStore(subscribeToDesktop, desktopSnapshot, serverSnapshot);
}
