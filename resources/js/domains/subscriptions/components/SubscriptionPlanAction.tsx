import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { isCompletePlan, type PlanName } from '@/lib/plan';
import { SwitchToFreeLauncher } from './SwitchToFreeLauncher';
import { UpgradeToCompleteLauncher } from './UpgradeToCompleteLauncher';

type Props = {
    planId: PlanName;
    currentPlan: PlanName;
    canCheckout: boolean;
    canSwitchToFree: boolean;
    accessEndsOn: string | null;
};

const ACTION_CLASS_NAME = 'w-full rounded-lg';

export function SubscriptionPlanAction({
    planId,
    currentPlan,
    canCheckout,
    canSwitchToFree,
    accessEndsOn,
}: Props) {
    const { t } = useTranslation('admin');

    if (planId === currentPlan) {
        return (
            <Button type="button" variant="outline" size="xl" disabled className={ACTION_CLASS_NAME}>
                {t('plan.settings.currentPlan')}
            </Button>
        );
    }

    if (isCompletePlan(planId)) {
        return canCheckout ? <UpgradeToCompleteLauncher className={ACTION_CLASS_NAME} /> : null;
    }

    if (canSwitchToFree) {
        return <SwitchToFreeLauncher accessEndsOn={accessEndsOn} className={ACTION_CLASS_NAME} />;
    }

    return null;
}
