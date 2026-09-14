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

export type Locale = 'es' | 'en';

/** The cookie Laravel reads, so it must stay readable and writable from here. */
export const LOCALE_COOKIE = 'locale';

const DEFAULT_LOCALE: Locale = 'es';

/**
 * The catalogue consulted for keys a locale does not define — not the language
 * the product speaks, which is Spanish.
 */
const FALLBACK_LOCALE: Locale = 'en';

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

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
 * The backend shares its own list, but a locale with no catalogue here would
 * only render as raw keys, so the two lists are intersected rather than trusted.
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
 * The Blade root view is rendered before React boots, so `<html lang>` is
 * corrected from here whenever the language settles or changes — screen readers,
 * `:lang()` rules and the browser's hyphenation all depend on it.
 */
function syncDocumentLanguage(language: string): void {
    document.documentElement.lang = language;
}

i18n.on('languageChanged', syncDocumentLanguage);

/** The shape Laravel expects, and the only place the cookie is written. */
function writeLocaleCookie(locale: Locale): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${LOCALE_COOKIE}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

/**
 * The product's last-resort default, expressed as a detector so it sits in the
 * same ordered chain as the cookie instead of being a special case elsewhere.
 */
const productDefaultDetector: CustomDetector = {
    name: 'productDefault',
    lookup: () => DEFAULT_LOCALE,
};

const languageDetector = new LanguageDetector();

languageDetector.addDetector(productDefaultDetector);

/**
 * Laravel already resolved the locale through `?lang=` → `X-Locale` → the
 * `locale` cookie → `es`, and answers every validation message in it. Detection
 * here is only the fallback for a missing or unrenderable prop; re-detecting
 * independently would let the page disagree with the server about one request.
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
            // Setting `lng` skips detection entirely, which is what makes the
            // server authoritative.
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
                // `navigator` is deliberately absent, mirroring the server: a
                // visitor with an English browser is still served Spanish until
                // they choose otherwise.
                order: ['cookie', 'productDefault'],
                lookupCookie: LOCALE_COOKIE,
                // `changeLocale` owns the cookie. Letting the detector cache as
                // well would give the same value two writers with two different
                // sets of cookie attributes.
                caches: [],
            },
            react: {
                // Every catalogue is already in the bundle, so nothing is ever
                // pending and Suspense would add a boundary with no purpose.
                useSuspense: false,
            },
        });

    syncDocumentLanguage(i18n.resolvedLanguage ?? DEFAULT_LOCALE);

    return i18n;
}

/**
 * The cookie is what tells Laravel to keep answering in the same language on the
 * next full page load and on every API call made from a fresh tab.
 */
export async function changeLocale(locale: Locale): Promise<void> {
    writeLocaleCookie(locale);

    await i18n.changeLanguage(locale);
}

export function currentLocale(): string {
    return i18n.resolvedLanguage ?? DEFAULT_LOCALE;
}

export { i18n };
