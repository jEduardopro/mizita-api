import { useTranslation } from 'react-i18next';
import { CurrentPlanSummary } from '@/components/admin/plan/CurrentPlanSummary';
import { PlanCard } from '@/components/admin/plan/PlanCard';
import { formatPlanEndDate } from '@/components/admin/plan/plan-dates';
import { FormStatus } from '@/components/form/FormStatus';
import type { PlanName } from '@/lib/plan';
import { PRICING_PLANS } from '@/lib/pricing-plans';
import { PaymentPastDueBanner } from './PaymentPastDueBanner';
import { paymentGraceDeadlineOf, standingOf } from './subscription-standing';
import { SubscriptionFailure } from './SubscriptionFailure';
import { SubscriptionPlanAction } from './SubscriptionPlanAction';
import { SubscriptionPlanSkeleton } from './SubscriptionPlanSkeleton';
import { SubscriptionStandingDetails } from './SubscriptionStandingDetails';
import { useCheckoutReturn } from './use-checkout-return';
import { useSubscription } from '../queries';

const PLAN_NAME_KEYS = {
    free: 'plan.names.free',
    complete: 'plan.names.complete',
} as const satisfies Record<PlanName, string>;

type Props = {
    timezone: string;
};

export function SubscriptionPlanOverview({ timezone }: Props) {
    const { t, i18n } = useTranslation('admin');
    const { data: subscription, isPending, isError, refetch } = useSubscription();
    const isConfirmingReturn = useCheckoutReturn();

    if (isPending) {
        return <SubscriptionPlanSkeleton />;
    }

    if (isError) {
        return <SubscriptionFailure message={t('plan.subscription.loadFailed')} onRetry={() => void refetch()} />;
    }

    const formatDate = (instant: string) => formatPlanEndDate(instant, timezone, i18n.language);
    const standing = standingOf(subscription);
    const graceDeadline = paymentGraceDeadlineOf(subscription);
    const periodEndsOn = subscription.current_period_ends_at === null
        ? null
        : formatDate(subscription.current_period_ends_at);
    const showsDetails = isConfirmingReturn || standing.kind !== 'free';

    return (
        <div className="@container/plan grid gap-8">
            {graceDeadline !== null ? <PaymentPastDueBanner graceEndsOn={formatDate(graceDeadline)} /> : null}

            <div className="grid gap-8 @min-[62rem]/plan:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] @min-[62rem]/plan:gap-12">
                <CurrentPlanSummary
                    planLabel={t(PLAN_NAME_KEYS[subscription.plan])}
                    details={showsDetails ? (
                        <>
                            {isConfirmingReturn ? <FormStatus message={t('plan.checkout.confirmingReturn')} /> : null}

                            <SubscriptionStandingDetails
                                standing={standing}
                                canChangeCard={subscription.can_manage_billing && graceDeadline === null}
                                formatDate={formatDate}
                            />
                        </>
                    ) : null}
                />

                <div className="grid max-w-4xl gap-4 @min-[36rem]/plan:grid-cols-2">
                    {PRICING_PLANS.map((pricingPlan) => (
                        <PlanCard
                            key={pricingPlan.id}
                            plan={pricingPlan}
                            isCurrent={pricingPlan.id === subscription.plan}
                            action={
                                <SubscriptionPlanAction
                                    planId={pricingPlan.id}
                                    currentPlan={subscription.plan}
                                    canCheckout={subscription.can_checkout}
                                    canSwitchToFree={subscription.can_switch_to_free}
                                    accessEndsOn={periodEndsOn}
                                />
                            }
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
