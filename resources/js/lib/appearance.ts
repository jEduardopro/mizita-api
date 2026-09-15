export type ResolvedAppearance = 'light' | 'dark';

export type Appearance = ResolvedAppearance | 'system';

export const APPEARANCE_COOKIE = 'appearance';

const DARK_SCHEME_QUERY = '(prefers-color-scheme: dark)';

const DARK_CLASS = 'dark';

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

let storedAppearance: Appearance | null = null;

const listeners = new Set<() => void>();

let darkScheme: MediaQueryList | null = null;

function darkSchemeQuery(): MediaQueryList {
    darkScheme ??= window.matchMedia(DARK_SCHEME_QUERY);

    return darkScheme;
}

export function resolveAppearance(appearance: Appearance): ResolvedAppearance {
    if (appearance !== 'system') {
        return appearance;
    }

    return darkSchemeQuery().matches ? 'dark' : 'light';
}

function applyAppearance(appearance: Appearance): void {
    document.documentElement.classList.toggle(
        DARK_CLASS,
        resolveAppearance(appearance) === 'dark',
    );
}

function handleSchemeChange(): void {
    if (storedAppearance === 'system') {
        applyAppearance(storedAppearance);
    }

    listeners.forEach((notify) => notify());
}

export function initializeAppearance(appearance: Appearance): void {
    storedAppearance = appearance;

    darkSchemeQuery().addEventListener('change', handleSchemeChange);

    listeners.forEach((notify) => notify());
}

function writeAppearanceCookie(appearance: Appearance): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${APPEARANCE_COOKIE}=${appearance}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

export function setAppearance(appearance: Appearance): void {
    writeAppearanceCookie(appearance);

    storedAppearance = appearance;

    applyAppearance(appearance);

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

    return () => {
        listeners.delete(notify);
    };
}

export function appearanceSnapshot(): ResolvedAppearance {
    return resolveAppearance(currentAppearance());
}
