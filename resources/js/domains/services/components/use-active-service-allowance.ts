import { usePlan } from '@/hooks/use-plan';
import { useActiveServiceQuota } from '../queries';

export type ActiveServiceAllowance =
    | { status: 'unlimited' }
    | { status: 'loading' }
    | { status: 'unavailable' }
    | { status: 'limited'; activeCount: number; limit: number; allowsAnother: boolean };

export function useActiveServiceAllowance(): ActiveServiceAllowance {
    const plan = usePlan();
    const quota = useActiveServiceQuota();

    if (plan.maxActiveServices === null) {
        return { status: 'unlimited' };
    }

    if (quota.isPending) {
        return { status: 'loading' };
    }

    if (quota.isError) {
        return { status: 'unavailable' };
    }

    return {
        status: 'limited',
        activeCount: quota.data.active_count,
        limit: plan.maxActiveServices,
        allowsAnother: plan.allowsAnotherActiveService(quota.data.active_count),
    };
}
