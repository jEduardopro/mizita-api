import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AccountCta } from '@/components/AccountCta';
import { BENEFITS_ID, BenefitGrid } from '@/components/BenefitGrid';
import { HeroCollage } from '@/components/HeroCollage';
import { HOW_IT_WORKS_ID, HowItWorks } from '@/components/HowItWorks';
import { PRICING_ID, PricingPlans } from '@/components/PricingPlans';
import { Section } from '@/components/Section';
import { PublicLayout } from '@/layouts/PublicLayout';

/**
 * The header menu. The labels are each section's own eyebrow, so the menu and the
 * section it points at can never end up saying different things.
 */
const menu = [
    { id: HOW_IT_WORKS_ID, labelKey: 'welcome.steps.eyebrow' },
    { id: BENEFITS_ID, labelKey: 'welcome.benefits.eyebrow' },
    { id: PRICING_ID, labelKey: 'welcome.pricing.eyebrow' },
] as const;

/**
 * The public landing page, rendered by `Inertia::render('public/welcome')`.
 *
 * Five sections, in the order a business owner asks the questions: what is this,
 * how do I start, what do I get, what does it cost, where do I sign. The hero
 * offers exactly one action, so the first screen asks a single question; logging
 * in stays in the header, where someone who already has an account looks for it.
 *
 * The surfaces alternate down the page — page, tinted, page, tinted, brand — and
 * the tone is declared here, on the section, rather than inside each component.
 * The band at the bottom is the only brand-tinted surface the page has, which is
 * how the last call to action ends up being the loudest thing on it.
 */
export default function Welcome() {
    const { name } = usePage().props;
    const { t } = useTranslation('public');

    const sections = menu.map(({ id, labelKey }) => ({ id, label: t(labelKey) }));

    return (
        <PublicLayout title={t('welcome.title')} sections={sections}>
            <Section>
                <div className="grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
                    <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500">
                        {/*
                         * The eyebrow is a pill rather than a line of small caps:
                         * it is the first brand-coloured thing on the page, and it
                         * sets up the blue the buttons below it are about to spend.
                         */}
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
