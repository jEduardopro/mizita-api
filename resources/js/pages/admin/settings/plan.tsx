import { useTranslation } from 'react-i18next';
import { CurrentPlanSummary } from '@/components/admin/plan/CurrentPlanSummary';
import { PlanCard } from '@/components/admin/plan/PlanCard';
import { PlanCardAction } from '@/components/admin/plan/PlanCardAction';
import { formatPlanEndDate } from '@/components/admin/plan/plan-dates';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { useAuthorization } from '@/hooks/use-authorization';
import { usePlan } from '@/hooks/use-plan';
import { AdminLayout } from '@/layouts/AdminLayout';
import type { PlanName } from '@/lib/plan';
import { PRICING_PLANS } from '@/lib/pricing-plans';
import { resolvedTimezone } from '@/lib/timezone';

const PLAN_NAME_KEYS = {
    free: 'plan.names.free',
    complete: 'plan.names.complete',
} as const satisfies Record<PlanName, string>;

function PlanOverview() {
    const { t, i18n } = useTranslation('admin');
    const plan = usePlan();
    const timezone = useBusinessTimezone() ?? resolvedTimezone();

    const endsOn = plan.endsAt === null
        ? null
        : formatPlanEndDate(plan.endsAt, timezone, i18n.language);

    return (
        <div className="@container/plan">
            <div className="grid gap-8 @min-[62rem]/plan:grid-cols-[minmax(0,16rem)_minmax(0,1fr)] @min-[62rem]/plan:gap-12">
                <CurrentPlanSummary planLabel={t(PLAN_NAME_KEYS[plan.name])} endsOn={endsOn} />

                <div className="grid max-w-4xl gap-4 @min-[36rem]/plan:grid-cols-2">
                    {PRICING_PLANS.map((pricingPlan) => (
                        <PlanCard
                            key={pricingPlan.id}
                            plan={pricingPlan}
                            isCurrent={pricingPlan.id === plan.name}
                            action={<PlanCardAction planId={pricingPlan.id} currentPlan={plan.name} />}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}

export default function PlanSettings() {
    const { t } = useTranslation('admin');
    const { is } = useAuthorization();

    return (
        <AdminLayout
            title={t('plan.settings.title')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('plan.settings.nav') },
            ]}
        >
            {is('owner') ? <PlanOverview /> : null}
        </AdminLayout>
    );
}
