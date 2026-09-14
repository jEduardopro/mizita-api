import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AccountCta } from '@/components/public/landing/AccountCta';
import { BENEFITS_ID, BenefitGrid } from '@/components/public/landing/BenefitGrid';
import { HOW_IT_WORKS_ID, HowItWorks } from '@/components/public/landing/HowItWorks';
import { PRICING_ID, PricingPlans } from '@/components/public/landing/PricingPlans';
import { Section } from '@/components/public/landing/Section';
import { HeroCollage } from '@/components/shared/hero/HeroCollage';
import { PublicLayout } from '@/layouts/PublicLayout';

/** The labels are each section's own eyebrow, so the two cannot drift. */
const menu = [
    { id: HOW_IT_WORKS_ID, labelKey: 'welcome.steps.eyebrow' },
    { id: BENEFITS_ID, labelKey: 'welcome.benefits.eyebrow' },
    { id: PRICING_ID, labelKey: 'welcome.pricing.eyebrow' },
] as const;

export default function Welcome() {
    const { name } = usePage().props;
    const { t } = useTranslation('public');

    const sections = menu.map(({ id, labelKey }) => ({ id, label: t(labelKey) }));

    return (
        <PublicLayout title={t('welcome.title')} sections={sections}>
            <Section>
                <div className="grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                    <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500">
                        <p className="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-brand-50 px-3 py-1 text-[0.6875rem] font-medium tracking-[0.18em] text-brand-700 uppercase dark:border-primary/25 dark:bg-brand-950/60 dark:text-brand-300">
                            <span aria-hidden="true" className="size-1.5 rounded-[2px] bg-primary" />
                            {t('welcome.eyebrow')}
                        </p>

                        <h1 className="mt-5 font-heading text-[clamp(2.5rem,7vw,4rem)] leading-[0.98] font-medium tracking-[-0.045em] text-balance">
                            {t('welcome.headline')}
                        </h1>

                        <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground">
                            {t('welcome.lede', { name })}
                        </p>

                        <AccountCta className="mt-8" size="xl" showLogIn={false} label={t('welcome.cta')} />
                    </div>

                    <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                        <HeroCollage />
                    </div>
                </div>
            </Section>

            <HowItWorks />

            <BenefitGrid />

            <PricingPlans />

            <Section tone="brand">
                <div className="text-center">
                    <h2 className="mx-auto max-w-lg font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance">
                        {t('welcome.closing.heading')}
                    </h2>

                    <p className="mx-auto mt-4 max-w-md text-base leading-relaxed text-muted-foreground">
                        {t('welcome.closing.body')}
                    </p>

                    <AccountCta className="mt-8 justify-center" size="xl" />
                </div>
            </Section>
        </PublicLayout>
    );
}
