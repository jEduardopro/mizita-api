import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { isCompletePlan, type PlanName } from '@/lib/plan';

type Props = {
    planId: PlanName;
    currentPlan: PlanName;
};

const ACTION_CLASS_NAME = 'w-full rounded-lg';

export function PlanCardAction({ planId, currentPlan }: Props) {
    const { t } = useTranslation('admin');

    if (planId === currentPlan) {
        return (
            <Button type="button" variant="outline" size="xl" disabled className={ACTION_CLASS_NAME}>
                {t('plan.settings.currentPlan')}
            </Button>
        );
    }

    if (isCompletePlan(planId)) {
        return (
            <Button type="button" variant="brand" size="xl" className={ACTION_CLASS_NAME}>
                {t('plan.settings.upgrade')}
            </Button>
        );
    }

    return null;
}
