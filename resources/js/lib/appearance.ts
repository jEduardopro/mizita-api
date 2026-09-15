export type ResolvedAppearance = 'light' | 'dark';

export type Appearance = ResolvedAppearance | 'system';

export const APPEARANCE_COOKIE = 'appearance';

const DARK_SCHEME_QUERY = '(prefers-color-scheme: dark)';

let storedAppearance: Appearance | null = null;

const listeners = new Set<() => void>();

function darkSchemeQuery(): MediaQueryList {
    return window.matchMedia(DARK_SCHEME_QUERY);
}

export function resolveAppearance(appearance: Appearance): ResolvedAppearance {
    if (appearance !== 'system') {
        return appearance;
    }

    return darkSchemeQuery().matches ? 'dark' : 'light';
}

export function initializeAppearance(appearance: Appearance): void {
    storedAppearance = appearance;

    listeners.forEach((notify) => notify());
}

export function currentAppearance(): Appearance {
    if (storedAppearance === null) {
        throw new Error('Appearance was read before initializeAppearance() ran.');
    }

    return storedAppearance;
}

export function subscribeToAppearance(notify: () => void): () => void {
    listeners.add(notify);

    const query = darkSchemeQuery();

    query.addEventListener('change', notify);

    return () => {
        listeners.delete(notify);
        query.removeEventListener('change', notify);
    };
}

export function appearanceSnapshot(): ResolvedAppearance {
    return resolveAppearance(currentAppearance());
}
