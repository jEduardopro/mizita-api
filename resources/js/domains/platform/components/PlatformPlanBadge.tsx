import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { PlatformBusinessPlan } from '../types';

const PLAN_LABEL_KEYS = {
    free: 'businesses.plan.free',
    complete: 'businesses.plan.complete',
} as const satisfies Record<PlatformBusinessPlan, string>;

const PLAN_CLASSES = {
    free: 'text-muted-foreground',
    complete: 'border-primary/25 bg-brand-50 text-primary dark:bg-brand-950/60',
} as const satisfies Record<PlatformBusinessPlan, string>;

type Props = {
    plan: PlatformBusinessPlan;
};

export function PlatformPlanBadge({ plan }: Props) {
    const { t } = useTranslation('platform');

    return (
        <Badge variant="outline" className={PLAN_CLASSES[plan]}>
            {t(PLAN_LABEL_KEYS[plan])}
        </Badge>
    );
}
