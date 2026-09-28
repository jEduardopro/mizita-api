import { cn } from 'cn';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PlanFeatureList } from '@/components/admin/plan/PlanFeatureList';
import {
    PRICING_CURRENCY_KEY,
    PRICING_PER_MONTH_KEY,
    PRICING_RECOMMENDED_KEY,
    type PricingPlan,
} from '@/lib/pricing-plans';

type Props = {
    plan: PricingPlan;
    isCurrent: boolean;
    action?: ReactNode;
};

export function PlanCard({ plan, isCurrent, action }: Props) {
    const { t } = useTranslation('public');

    const nameId = `plan-${plan.id}-name`;

    return (
        <article
            aria-labelledby={nameId}
            className={cn(
                'flex min-w-0 flex-col rounded-xl border bg-card p-5 sm:p-6',
                isCurrent
                    ? 'border-primary/30 shadow-lg shadow-primary/5 ring-1 ring-primary/15'
                    : 'border-border',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3
                    id={nameId}
                    className="font-heading text-2xl leading-snug font-medium tracking-[-0.015em]"
                >
                    {t(plan.nameKey)}
                </h3>

                {plan.recommended ? (
                    <span className="rounded-full bg-primary px-2.5 py-0.5 text-[0.6875rem] font-medium tracking-[0.14em] text-primary-foreground uppercase">
                        {t(PRICING_RECOMMENDED_KEY)}
                    </span>
                ) : null}
            </div>

            <p className="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                {t(plan.descriptionKey)}
            </p>

            <p className="mt-6 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span className="font-heading text-[clamp(2rem,4.5vw,2.5rem)] leading-none font-medium tracking-[-0.045em] tabular-nums">
                    {t(plan.priceKey)}
                </span>

                <span className="text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                    {t(PRICING_CURRENCY_KEY)}
                </span>

                <span className="text-sm text-muted-foreground">{t(PRICING_PER_MONTH_KEY)}</span>
            </p>

            <p className="mt-6 border-t border-border pt-5 text-[0.6875rem] tracking-[0.14em] text-muted-foreground uppercase">
                {t(plan.includesKey)}
            </p>

            <PlanFeatureList features={plan.features} />

            {action ? <div className="mt-auto pt-7">{action}</div> : null}
        </article>
    );
}
