import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { AccountCta } from '@/components/public/landing/AccountCta';
import { Section } from '@/components/public/landing/Section';
import { Badge } from '@/components/ui/badge';
import {
    PRICING_COMING_SOON_KEY,
    PRICING_CURRENCY_KEY,
    PRICING_PER_MONTH_KEY,
    PRICING_PLANS,
    PRICING_RECOMMENDED_KEY,
} from '@/lib/pricing-plans';

export const PRICING_ID = 'pricing';

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
                {PRICING_PLANS.map((plan) => (
                    <article
                        key={plan.id}
                        className={cn(
                            'flex flex-col rounded-xl border bg-card p-6 sm:p-7',
                            plan.recommended
                                ? 'border-primary/30 shadow-lg shadow-primary/5 ring-1 ring-primary/15'
                                : 'border-border',
                        )}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h3 className="font-heading text-3xl leading-snug font-medium tracking-[-0.015em]">
                                {t(plan.nameKey)}
                            </h3>

                            {plan.recommended ? (
                                <span className="rounded-full bg-primary px-2.5 py-0.5 text-[0.6875rem] font-medium tracking-[0.14em] text-primary-foreground uppercase">
                                    {t(PRICING_RECOMMENDED_KEY)}
                                </span>
                            ) : null}
                        </div>

                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                            {t(plan.descriptionKey)}
                        </p>

                        <p className="mt-7 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span className="font-heading text-[clamp(2.25rem,5vw,2.75rem)] leading-none font-medium tracking-[-0.045em] tabular-nums">
                                {t(plan.priceKey)}
                            </span>

                            <span className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                                {t(PRICING_CURRENCY_KEY)}
                            </span>

                            <span className="text-sm text-muted-foreground">
                                {t(PRICING_PER_MONTH_KEY)}
                            </span>
                        </p>

                        <p className="mt-7 border-t border-border pt-6 text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                            {t(plan.includesKey)}
                        </p>

                        <ul className="mt-4 space-y-2.5">
                            {plan.features.map((feature) => (
                                <li key={feature.key} className="flex gap-3 text-sm leading-relaxed text-muted-foreground">
                                    <span
                                        aria-hidden="true"
                                        className="mt-[0.4375rem] size-1.5 shrink-0 rounded-[2px] bg-primary/60"
                                    />
                                    <span>
                                        {t(feature.key)}
                                        {feature.comingSoon ? (
                                            <Badge
                                                variant="outline"
                                                className="ml-2 align-[0.0625rem] text-[0.625rem] tracking-[0.12em] text-muted-foreground uppercase"
                                            >
                                                {t(PRICING_COMING_SOON_KEY)}
                                            </Badge>
                                        ) : null}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <AccountCta
                            className="mt-auto w-full pt-8 *:w-full *:rounded-lg"
                            size="xl"
                            variant={plan.recommended ? 'brand' : 'brand-outline'}
                            showLogIn={false}
                            label={t(plan.ctaKey)}
                        />
                    </article>
                ))}
            </div>
        </Section>
    );
}
