import { loadStripe, type Stripe, type StripeElementLocale } from '@stripe/stripe-js';
import { currentLocale, type Locale } from '@/lib/i18n';

const STRIPE_LOCALES = {
    es: 'es',
    en: 'en',
} as const satisfies Record<Locale, StripeElementLocale>;

const AUTOMATIC_LOCALE: StripeElementLocale = 'auto';

let pendingStripe: Promise<Stripe | null> | null = null;

function isSupportedLocale(locale: string): locale is Locale {
    return Object.hasOwn(STRIPE_LOCALES, locale);
}

function stripeLocaleOf(locale: string): StripeElementLocale {
    return isSupportedLocale(locale) ? STRIPE_LOCALES[locale] : AUTOMATIC_LOCALE;
}

export function stripePublishableKey(): string | null {
    const key = import.meta.env.VITE_STRIPE_KEY;

    return key === undefined || key === '' ? null : key;
}

export function stripeClient(publishableKey: string): Promise<Stripe | null> {
    pendingStripe ??= loadStripe(publishableKey, { locale: stripeLocaleOf(currentLocale()) }).catch(
        (error: unknown) => {
            pendingStripe = null;

            throw error;
        },
    );

    return pendingStripe;
}
