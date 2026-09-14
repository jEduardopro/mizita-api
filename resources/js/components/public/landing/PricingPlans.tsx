import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { AccountCta } from '@/components/public/landing/AccountCta';
import { Section } from '@/components/public/landing/Section';

export const PRICING_ID = 'pricing';

const plans = [
    {
        name: 'free',
        recommended: false,
        cta: 'welcome.pricing.plans.free.cta',
        features: [
            'welcome.pricing.plans.free.features.staff',
            'welcome.pricing.plans.free.features.services',
            'welcome.pricing.plans.free.features.hours',
            'welcome.pricing.plans.free.features.page',
            'welcome.pricing.plans.free.features.slots',
        ],
    },
    {
        name: 'complete',
        recommended: true,
        cta: 'welcome.pricing.plans.complete.cta',
        features: [
            'welcome.pricing.plans.complete.features.staff',
            'welcome.pricing.plans.complete.features.services',
            'welcome.pricing.plans.complete.features.timeOff',
            'welcome.pricing.plans.complete.features.policy',
            'welcome.pricing.plans.complete.features.agenda',
            'welcome.pricing.plans.complete.features.reminders',
            'welcome.pricing.plans.complete.features.payments',
            'welcome.pricing.plans.complete.features.reports',
            'welcome.pricing.plans.complete.features.googleCalendar',
        ],
    },
] as const;

export function PricingPlans() {
    const { t } = useTranslation('public');

    return (
        <Section id={PRICING_ID} tone="muted">
            <p className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase">
                {t('welcome.pricing.eyebrow')}
            </p>

            <h2
                id={`${PRICING_ID}-heading`}
                className="mt-4 max-w-xl font-heading text-[clamp(1.75rem,4vw,2.5rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance"
            >
                {t('welcome.pricing.heading')}
            </h2>

            <div className="mx-auto mt-10 grid max-w-4xl gap-4 sm:mt-12 sm:grid-cols-2">
                {plans.map((plan) => (
                    <article
                        key={plan.name}
                        className={cn(
                            'flex flex-col rounded-xl border bg-card p-6 sm:p-7',
                            plan.recommended
                                ? 'border-primary/30 shadow-lg shadow-primary/5 ring-1 ring-primary/15'
                                : 'border-border',
                        )}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h3 className="font-heading text-3xl leading-snug font-medium tracking-[-0.015em]">
                                {t(`welcome.pricing.plans.${plan.name}.name`)}
                            </h3>

                            {plan.recommended ? (
                                <span className="rounded-full bg-primary px-2.5 py-0.5 text-[0.6875rem] font-medium tracking-[0.14em] text-primary-foreground uppercase">
                                    {t('welcome.pricing.recommended')}
                                </span>
                            ) : null}
                        </div>

                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            {t(`welcome.pricing.plans.${plan.name}.summary`)}
                        </p>

                        <p className="mt-7 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span className="font-heading text-[clamp(2.25rem,5vw,2.75rem)] leading-none font-medium tracking-[-0.045em] tabular-nums">
                                {t(`welcome.pricing.plans.${plan.name}.price`)}
                            </span>

                            <span className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                {t('welcome.pricing.currency')}
                            </span>

                            <span className="text-sm text-muted-foreground">
                                {t('welcome.pricing.perMonth')}
                            </span>
                        </p>

                        <p className="mt-7 border-t border-border pt-6 text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                            {t(`welcome.pricing.plans.${plan.name}.includes`)}
                        </p>

                        <ul className="mt-4 space-y-2.5">
                            {plan.features.map((feature) => (
                                <li key={feature} className="flex gap-3 text-sm leading-relaxed text-muted-foreground">
                                    <span
                                        aria-hidden="true"
                                        className="mt-[0.4375rem] size-1.5 shrink-0 rounded-[2px] bg-primary/60"
                                    />
                                    {t(feature)}
                                </li>
                            ))}
                        </ul>

                        <AccountCta
                            className="mt-auto w-full pt-8 *:w-full *:rounded-lg"
                            size="xl"
                            variant={plan.recommended ? 'brand' : 'brand-outline'}
                            showLogIn={false}
                            label={t(plan.cta)}
                        />
                    </article>
                ))}
            </div>
        </Section>
    );
}
