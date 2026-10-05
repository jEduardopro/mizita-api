import i18n, { type ResourceKey, type ResourceLanguage } from 'i18next';
import { initReactI18next } from 'react-i18next';

export type Locale = 'es' | 'en';

export const LOCALE_COOKIE = 'locale';

const DEFAULT_LOCALE: Locale = 'es';

const FALLBACK_LOCALE: Locale = 'en';

const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;

export const namespaces = ['common', 'auth', 'public', 'admin', 'industries', 'platform'] as const;

type Namespace = (typeof namespaces)[number];

type BundleLoader = () => Promise<{ default: ResourceKey }>;

const bundleLoaders: Record<Locale, Record<Namespace, BundleLoader>> = {
    es: {
        common: () => import('@/locales/es/common.json'),
        auth: () => import('@/locales/es/auth.json'),
        public: () => import('@/locales/es/public.json'),
        admin: () => import('@/locales/es/admin.json'),
        industries: () => import('@/locales/es/industries.json'),
        platform: () => import('@/locales/es/platform.json'),
    },
    en: {
        common: () => import('@/locales/en/common.json'),
        auth: () => import('@/locales/en/auth.json'),
        public: () => import('@/locales/en/public.json'),
        admin: () => import('@/locales/en/admin.json'),
        industries: () => import('@/locales/en/industries.json'),
        platform: () => import('@/locales/en/platform.json'),
    },
};

const bundledLocales = Object.keys(bundleLoaders) as Locale[];

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

function readLocaleCookie(): string | undefined {
    const prefix = `${LOCALE_COOKIE}=`;

    return document.cookie
        .split(';')
        .map((entry) => entry.trim())
        .find((entry) => entry.startsWith(prefix))
        ?.slice(prefix.length);
}

function resolveInitialLocale(serverLocale: string | undefined, supported: Locale[]): Locale {
    if (isBundledLocale(serverLocale)) {
        return serverLocale;
    }

    const candidates = [readLocaleCookie(), DEFAULT_LOCALE];

    return candidates.find((candidate): candidate is Locale =>
        isBundledLocale(candidate) && supported.includes(candidate),
    ) ?? FALLBACK_LOCALE;
}

async function loadLocaleBundles(locale: Locale): Promise<ResourceLanguage> {
    const loaders = bundleLoaders[locale];

    const bundles = await Promise.all(
        namespaces.map(async (namespace) => [namespace, (await loaders[namespace]()).default] as const),
    );

    return Object.fromEntries(bundles);
}

function isLocaleLoaded(locale: Locale): boolean {
    return namespaces.every((namespace) => i18n.hasResourceBundle(locale, namespace));
}

async function ensureLocaleLoaded(locale: Locale): Promise<void> {
    if (isLocaleLoaded(locale)) {
        return;
    }

    const bundles = await loadLocaleBundles(locale);

    for (const namespace of namespaces) {
        i18n.addResourceBundle(locale, namespace, bundles[namespace], true, true);
    }
}

function syncDocumentLanguage(language: string): void {
    document.documentElement.lang = language;
}

i18n.on('languageChanged', syncDocumentLanguage);

function writeLocaleCookie(locale: Locale): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `${LOCALE_COOKIE}=${locale}; Path=/; Max-Age=${COOKIE_MAX_AGE_SECONDS}; SameSite=Lax${secure}`;
}

let initialization: Promise<typeof i18n> | undefined;

async function initialize(locale: string | undefined, supportedLocales: string[] | undefined): Promise<typeof i18n> {
    const supported = resolveSupportedLocales(supportedLocales);
    const language = resolveInitialLocale(locale, supported);

    await i18n.use(initReactI18next).init({
        resources: { [language]: await loadLocaleBundles(language) },
        lng: language,
        fallbackLng: FALLBACK_LOCALE,
        supportedLngs: supported,
        ns: namespaces,
        defaultNS: 'common',
        interpolation: {
            escapeValue: false,
        },
        react: {
            useSuspense: false,
        },
    });

    syncDocumentLanguage(i18n.resolvedLanguage ?? DEFAULT_LOCALE);

    return i18n;
}

export function initI18n(locale?: string, supportedLocales?: string[]): Promise<typeof i18n> {
    initialization ??= initialize(locale, supportedLocales).catch((error: unknown) => {
        initialization = undefined;

        throw error;
    });

    return initialization;
}

export async function changeLocale(locale: Locale): Promise<void> {
    await ensureLocaleLoaded(locale);

    writeLocaleCookie(locale);

    await i18n.changeLanguage(locale);
}

export function currentLocale(): string {
    return i18n.resolvedLanguage ?? DEFAULT_LOCALE;
}

export { i18n };
