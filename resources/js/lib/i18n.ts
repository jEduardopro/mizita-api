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

export const LOCALE_COOKIE = 'locale';

const DEFAULT_LOCALE: Locale = 'es';

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

function syncDocumentLanguage(language: string): void {
    document.documentElement.lang = language;
}

i18n.on('languageChanged', syncDocumentLanguage);

function writeLocaleCookie(locale: Locale): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${LOCALE_COOKIE}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

const productDefaultDetector: CustomDetector = {
    name: 'productDefault',
    lookup: () => DEFAULT_LOCALE,
};

const languageDetector = new LanguageDetector();

languageDetector.addDetector(productDefaultDetector);

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
            lng: isBundledLocale(locale) ? locale : undefined,
            fallbackLng: FALLBACK_LOCALE,
            supportedLngs: supported,
            ns: namespaces,
            defaultNS: 'common',
            interpolation: {
                escapeValue: false,
            },
            detection: {
                order: ['cookie', 'productDefault'],
                lookupCookie: LOCALE_COOKIE,
                caches: [],
            },
            react: {
                useSuspense: false,
            },
        });

    syncDocumentLanguage(i18n.resolvedLanguage ?? DEFAULT_LOCALE);

    return i18n;
}

export async function changeLocale(locale: Locale): Promise<void> {
    writeLocaleCookie(locale);

    await i18n.changeLanguage(locale);
}

export function currentLocale(): string {
    return i18n.resolvedLanguage ?? DEFAULT_LOCALE;
}

export { i18n };
