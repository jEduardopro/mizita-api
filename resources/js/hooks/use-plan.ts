import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    allowsAnotherActiveService,
    FREE_PLAN,
    isCompletePlan,
    planIncludes,
    type PlanFeature,
    type PlanName,
} from '@/lib/plan';

export type PlanAccess = {
    name: PlanName;
    isComplete: boolean;
    endsAt: string | null;
    includes: (feature: PlanFeature) => boolean;
    maxActiveServices: number | null;
    allowsAnotherActiveService: (activeCount: number) => boolean;
};

export function usePlan(): PlanAccess {
    const { plan } = usePage().props;
    const current = plan ?? FREE_PLAN;

    return useMemo<PlanAccess>(
        () => ({
            name: current.name,
            isComplete: isCompletePlan(current.name),
            endsAt: current.ends_at,
            includes: (feature) => planIncludes(feature, current.entitlements),
            maxActiveServices: current.entitlements.max_active_services,
            allowsAnotherActiveService: (activeCount) =>
                allowsAnotherActiveService(activeCount, current.entitlements.max_active_services),
        }),
        [current],
    );
}
