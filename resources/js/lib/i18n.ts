import i18n from 'i18next';
import LanguageDetector, { type CustomDetector } from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import adminEn from '@/locales/en/admin.json';
import authEn from '@/locales/en/auth.json';
import commonEn from '@/locales/en/common.json';
import industriesEn from '@/locales/en/industries.json';
import publicEn from '@/locales/en/public.json';
import adminEs from '@/locales/es/admin.json';
import authEs from '@/locales/es/auth.json';
import commonEs from '@/locales/es/common.json';
import industriesEs from '@/locales/es/industries.json';
import publicEs from '@/locales/es/public.json';

/**
 * The single i18n setup for the whole front end.
 *
 * Translations are imported statically rather than fetched over HTTP. The whole
 * catalogue is a few kilobytes, and bundling it means the very first paint is
 * already in the visitor's language: no loading flash, no English frame that
 * swaps to Spanish a tick later, and nothing to re-render once a request lands.
 *
 * Spanish is the product's primary language and English is the fallback, which
 * is why `fallbackLng` below is `en`: that option answers "which catalogue do I
 * read when a key is missing here?", not "which language is the product in".
 * Choosing the language is the backend's job, see `initI18n`.
 */

export type Locale = 'es' | 'en';

/** The cookie Laravel reads, so a change made here is a change the server sees. */
export const LOCALE_COOKIE = 'locale';

/** The locale the product falls back to when nothing at all has been decided. */
const DEFAULT_LOCALE: Locale = 'es';

/** The catalogue consulted for keys a locale does not define. */
const FALLBACK_LOCALE: Locale = 'en';

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

/**
 * Namespaces mirror the app's own structure: one per audience folder under
 * `pages/`, plus the ones that are shared vocabulary rather than an audience.
 * `common` is the chrome every surface draws; `industries` is the catalogue the
 * backend keys by `key` and never labels, so the words for it belong here and
 * are read wherever an industry is shown — today the onboarding form, tomorrow
 * the public directory.
 */
export const namespaces = ['common', 'auth', 'public', 'admin', 'industries'] as const;

const resources = {
    es: {
        common: commonEs,
        auth: authEs,
        public: publicEs,
        admin: adminEs,
        industries: industriesEs,
    },
    en: {
        common: commonEn,
        auth: authEn,
        public: publicEn,
        admin: adminEn,
        industries: industriesEn,
    },
};

/**
 * The locales this bundle can actually render. The backend shares its own list,
 * but a locale with no catalogue here would only render as raw keys, so the two
 * lists are intersected rather than trusted blindly.
 */
const bundledLocales = Object.keys(resources) as Locale[];

function isBundledLocale(value: string | undefined): value is Locale {
    return value !== undefined && (bundledLocales as string[]).includes(value);
}

function resolveSupportedLocales(supportedLocales: string[] | undefined): Locale[] {
    if (supportedLocales === undefined) {
        return bundledLocales;
    }

    const supported = supportedLocales.filter(isBundledLocale);

    return supported.length > 0 ? supported : bundledLocales;
}

/**
 * Keeps `<html lang>` honest. The Blade root view belongs to the backend and is
 * rendered before React boots, so the attribute is corrected from here whenever
 * the language settles or changes — screen readers and `:lang()` rules both
 * depend on it, and so does the browser's own hyphenation.
 */
function syncDocumentLanguage(language: string): void {
    document.documentElement.lang = language;
}

i18n.on('languageChanged', syncDocumentLanguage);

/**
 * Writes the locale cookie in the shape Laravel expects: same name, root path,
 * one year. Nothing else writes it — the detector's own cache is switched off
 * below precisely so this stays the only writer.
 */
function writeLocaleCookie(locale: Locale): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${LOCALE_COOKIE}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

/**
 * The product's last-resort default, expressed as a detector so it sits in the
 * same ordered chain as the cookie instead of being a special case somewhere
 * else.
 */
const productDefaultDetector: CustomDetector = {
    name: 'productDefault',
    lookup: () => DEFAULT_LOCALE,
};

const languageDetector = new LanguageDetector();

languageDetector.addDetector(productDefaultDetector);

/**
 * Boots i18next. Called from `app.tsx` with the locale the backend shared, before
 * anything renders.
 *
 * The server's decision wins over any client-side detection, and that ordering is
 * deliberate. Laravel already resolved the locale through `?lang=` → `X-Locale` →
 * the `locale` cookie → `es`, and it used that answer to pick the language of every
 * validation message, mail and redirect it will send. If the client re-detected
 * independently it could reach a different conclusion — a stale cookie read
 * differently, a default weighted differently — and the page would then disagree
 * with the server about the same request. One decision, made once, upstream.
 *
 * Every step of that chain is an explicit choice, because the product is
 * Spanish-first: it answers in Spanish until someone asks for something else, and
 * never guesses from the browser's own language settings.
 *
 * Detection is still configured because it is the honest fallback for the case
 * where the prop is absent or names a locale this bundle cannot render.
 */
export function initI18n(locale?: string, supportedLocales?: string[]): typeof i18n {
    if (i18n.isInitialized) {
        return i18n;
    }

    const supported = resolveSupportedLocales(supportedLocales);

    void i18n
        .use(languageDetector)
        .use(initReactI18next)
        .init({
            resources,
            // Setting `lng` skips detection entirely, which is exactly what
            // makes the server authoritative.
            lng: isBundledLocale(locale) ? locale : undefined,
            fallbackLng: FALLBACK_LOCALE,
            supportedLngs: supported,
            ns: namespaces,
            defaultNS: 'common',
            interpolation: {
                // React escapes everything it renders, so escaping here would
                // only produce double-encoded entities.
                escapeValue: false,
            },
            detection: {
                // `navigator` is deliberately absent, mirroring the server: the
                // product is Spanish-first, so a visitor with an English browser
                // is still served Spanish until they choose otherwise. Inferring
                // the language from something nobody consciously set is exactly
                // the behaviour the backend dropped, and leaving it here would
                // reintroduce it on the one path that actually runs detection.
                order: ['cookie', 'productDefault'],
                lookupCookie: LOCALE_COOKIE,
                // `changeLocale` owns the cookie. Letting the detector cache as
                // well would give the same value two writers with two different
                // sets of cookie attributes.
                caches: [],
            },
            react: {
                // Every catalogue is already in the bundle, so nothing is ever
                // pending and Suspense would only add a boundary with no purpose.
                useSuspense: false,
            },
        });

    syncDocumentLanguage(i18n.resolvedLanguage ?? DEFAULT_LOCALE);

    return i18n;
}

/**
 * Switches language for the rest of the session.
 *
 * Both halves matter: i18next re-renders the app, and the cookie is what tells
 * Laravel to keep answering in the same language on the next full page load and
 * on every API call made from a fresh tab. No switcher UI ships yet; the plumbing
 * exists so one can be added without touching this file.
 */
export async function changeLocale(locale: Locale): Promise<void> {
    writeLocaleCookie(locale);

    await i18n.changeLanguage(locale);
}

/**
 * The language currently in effect, for callers outside React — the axios
 * instance sends it as `X-Locale` on every request.
 */
export function currentLocale(): string {
    return i18n.resolvedLanguage ?? DEFAULT_LOCALE;
}

export { i18n };
