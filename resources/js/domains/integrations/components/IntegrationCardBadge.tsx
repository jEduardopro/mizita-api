import { usePlan } from '@/hooks/use-plan';
import type { PlanFeature } from '@/lib/plan';
import type { ConnectionStatus } from '../types';
import { IntegrationStatusBadge } from './IntegrationStatusBadge';
import { PlanLockedBadge } from './PlanLockedBadge';

type Props = {
    status: ConnectionStatus | null;
    planFeature: PlanFeature;
};

export function IntegrationCardBadge({ status, planFeature }: Props) {
    const { includes } = usePlan();
    const isIncluded = includes(planFeature);

    if (! isIncluded && status === null) {
        return <PlanLockedBadge />;
    }

    if (! isIncluded) {
        return <IntegrationStatusBadge status="sync_paused" />;
    }

    if (status === null) {
        return null;
    }

    return <IntegrationStatusBadge status={status} />;
}
