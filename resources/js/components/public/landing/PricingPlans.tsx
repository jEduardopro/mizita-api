import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { AccountCta } from '@/components/public/landing/AccountCta';
import { Section } from '@/components/public/landing/Section';

/** Exported for the header menu, so the anchor is written down once. */
export const PRICING_ID = 'pricing';

/**
 * Two plans, and the line between them is the team: one person running their own
 * agenda pays nothing, a business with staff pays per month.
 *
 * The features and the call to action are listed one key at a time instead of
 * being read out of the catalogue as an array, which is what keeps `t()` checked
 * against the catalogue shape — a renamed key stops compiling here rather than
 * rendering an empty bullet.
 *
 * The paid list is deliberately the longer one: it is what the free plan does
 * not include, so the visible difference between the two cards is the argument
 * for upgrading. Side by side the grid stretches both to the taller card's
 * height, and `mt-auto` on the action is what spends that slack above the
 * button rather than below it, so the two buttons still land on one line.
 */
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

            {/*
             * The grid sits inside the section's own width rather than filling
             * it, so the two cards read as a choice between two things and not
             * as two panels that happen to be side by side.
             */}
            <div className="mx-auto mt-10 grid max-w-4xl gap-4 sm:mt-12 sm:grid-cols-2">
                {plans.map((plan) => (
                    <article
                        key={plan.name}
                        className={cn(
                            'flex flex-col rounded-xl border bg-card p-6 sm:p-7',
                            // Both cards lift off the tinted band; only the
                            // recommended one is drawn in the brand colour. The
                            // page now has an accent to spend, and the plan a
                            // business is meant to pick is exactly what it is
                            // for — the ring does the pointing so the copy does
                            // not have to.
                            plan.recommended
                                ? 'border-primary/30 shadow-lg shadow-primary/5 ring-1 ring-primary/15'
                                : 'border-border',
                        )}
                    >
                        {/*
                         * The plan name and the badge are each a single word, so
                         * the row cannot break inside either one. At the plan
                         * name's size that makes the pair the widest thing the
                         * card demands, and on a 320px screen that is what used
                         * to push the whole grid sideways. Letting the row wrap
                         * costs nothing at any real width and puts the badge on
                         * its own line at the narrowest one.
                         */}
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

                        {/*
                         * The amount, the currency and the period read as one
                         * sentence, so they share a baseline. The figure is
                         * tabular because the two cards sit side by side and
                         * their digits should line up.
                         */}
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
                                    {/*
                                     * The same small square the wordmark uses
                                     * for a booked block, so a feature list
                                     * is marked in the product's own shape
                                     * rather than with a borrowed tick.
                                     */}
                                    <span
                                        aria-hidden="true"
                                        className="mt-[0.4375rem] size-1.5 shrink-0 rounded-[2px] bg-primary/60"
                                    />
                                    {t(feature)}
                                </li>
                            ))}
                        </ul>

                        {/*
                         * `mt-auto` keeps both cards' buttons on one line, whatever
                         * the copy does above them, and `*:w-full` stretches the
                         * single button inside to the card's own width — a card is
                         * one offer, so its action spans it.
                         *
                         * `xl` is the hero's own button height, so the action a
                         * visitor is meant to press is no longer the smallest
                         * thing in the card. Its rounded-full corner belongs to a
                         * standalone pill, not to a button spanning a card, so the
                         * radius is brought back to the card's own here — the only
                         * thing the call site overrides.
                         */}
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
