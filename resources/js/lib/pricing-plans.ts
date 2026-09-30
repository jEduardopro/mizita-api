import type { ParseKeys } from 'i18next';

type PublicTranslationKey = ParseKeys<'public'>;

export type PricingPlanId = 'free' | 'complete';

export type PricingPlanFeature = {
    key: PublicTranslationKey;
    comingSoon: boolean;
};

export type PricingPlan = {
    id: PricingPlanId;
    recommended: boolean;
    nameKey: PublicTranslationKey;
    descriptionKey: PublicTranslationKey;
    priceKey: PublicTranslationKey;
    includesKey: PublicTranslationKey;
    ctaKey: PublicTranslationKey;
    features: readonly PricingPlanFeature[];
};

export const PRICING_CURRENCY_KEY = 'welcome.pricing.currency' satisfies PublicTranslationKey;

export const PRICING_PER_MONTH_KEY = 'welcome.pricing.perMonth' satisfies PublicTranslationKey;

export const PRICING_RECOMMENDED_KEY = 'welcome.pricing.recommended' satisfies PublicTranslationKey;

export const PRICING_COMING_SOON_KEY = 'welcome.pricing.comingSoon' satisfies PublicTranslationKey;

export const PRICING_PLANS = [
    {
        id: 'free',
        recommended: false,
        nameKey: 'welcome.pricing.plans.free.name',
        descriptionKey: 'welcome.pricing.plans.free.summary',
        priceKey: 'welcome.pricing.plans.free.price',
        includesKey: 'welcome.pricing.plans.free.includes',
        ctaKey: 'welcome.pricing.plans.free.cta',
        features: [
            { key: 'welcome.pricing.plans.free.features.staff', comingSoon: false },
            { key: 'welcome.pricing.plans.free.features.services', comingSoon: false },
            { key: 'welcome.pricing.plans.free.features.hours', comingSoon: false },
            { key: 'welcome.pricing.plans.free.features.page', comingSoon: false },
            { key: 'welcome.pricing.plans.free.features.slots', comingSoon: false },
            { key: 'welcome.pricing.plans.free.features.statistics', comingSoon: false },
        ],
    },
    {
        id: 'complete',
        recommended: true,
        nameKey: 'welcome.pricing.plans.complete.name',
        descriptionKey: 'welcome.pricing.plans.complete.summary',
        priceKey: 'welcome.pricing.plans.complete.price',
        includesKey: 'welcome.pricing.plans.complete.includes',
        ctaKey: 'welcome.pricing.plans.complete.cta',
        features: [
            { key: 'welcome.pricing.plans.complete.features.staff', comingSoon: false },
            { key: 'welcome.pricing.plans.complete.features.services', comingSoon: false },
            { key: 'welcome.pricing.plans.complete.features.timeOff', comingSoon: true },
            { key: 'welcome.pricing.plans.complete.features.policy', comingSoon: false },
            { key: 'welcome.pricing.plans.complete.features.agenda', comingSoon: true },
            { key: 'welcome.pricing.plans.complete.features.reminders', comingSoon: true },
            { key: 'welcome.pricing.plans.complete.features.payments', comingSoon: true },
            { key: 'welcome.pricing.plans.complete.features.googleCalendar', comingSoon: false },
        ],
    },
] as const satisfies readonly PricingPlan[];
