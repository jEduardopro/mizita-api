import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { formatMoneyFromCents } from '@/lib/money';
import type { Plan, PlanKey } from '../types';

const PLAN_NAME_KEYS = {
    complete: 'plan.names.complete',
} as const satisfies Record<PlanKey, string>;

const TITLE_ID = 'checkout-plan-details-title';

type Props = {
    plan: Plan | undefined;
};

type BodyProps = {
    plan: Plan;
};

function chargedTodayCents(plan: Plan): number {
    return plan.trial_days === null ? plan.price_cents : 0;
}

function CheckoutPlanDetailsBody({ plan }: BodyProps) {
    const { t } = useTranslation('admin');
    const formatPrice = (cents: number) => formatMoneyFromCents(cents, plan.currency_code);

    return (
        <>
            <div className="mt-4 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p className="font-heading text-2xl leading-snug font-medium tracking-[-0.015em]">
                    {t(PLAN_NAME_KEYS[plan.key])}
                </p>

                <p className="text-sm text-muted-foreground tabular-nums">
                    {t('plan.checkout.details.perMonth', { price: formatPrice(plan.price_cents) })}
                </p>
            </div>

            {plan.trial_days !== null ? (
                <p className="mt-2 text-sm font-medium text-primary">
                    {t('plan.checkout.details.trial', { count: plan.trial_days })}
                </p>
            ) : null}

            <dl className="mt-6 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-dashed border-border pt-5">
                <dt className="text-sm font-medium text-foreground">{t('plan.checkout.details.totalToday')}</dt>

                <dd className="font-heading text-[clamp(1.75rem,4vw,2.25rem)] leading-none font-medium tracking-[-0.04em] tabular-nums">
                    {formatPrice(chargedTodayCents(plan))}
                </dd>
            </dl>

            <p className="mt-4 text-xs leading-relaxed text-pretty text-muted-foreground">
                {t('plan.checkout.details.billing')}
            </p>
        </>
    );
}

function CheckoutPlanDetailsSkeleton() {
    return (
        <div aria-hidden="true" className="mt-4 grid gap-4">
            <Skeleton className="h-7 w-32" />
            <Skeleton className="h-4 w-24" />
            <Skeleton className="mt-4 h-9 w-full" />
        </div>
    );
}

export function CheckoutPlanDetails({ plan }: Props) {
    const { t } = useTranslation('admin');

    return (
        <section
            aria-labelledby={TITLE_ID}
            aria-busy={plan === undefined}
            className="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6"
        >
            <h2
                id={TITLE_ID}
                className="text-[0.6875rem] font-medium tracking-[0.18em] text-primary uppercase"
            >
                {t('plan.checkout.details.title')}
            </h2>

            {plan === undefined ? <CheckoutPlanDetailsSkeleton /> : <CheckoutPlanDetailsBody plan={plan} />}
        </section>
    );
}
