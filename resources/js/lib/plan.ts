export type PlanName = 'free' | 'complete';

export type PlanFeature = 'team' | 'booking_rules' | 'calendar_sync';

export type PlanEntitlements = {
    team: boolean;
    max_active_services: number | null;
    booking_rules: boolean;
    calendar_sync: boolean;
};

export type SharedPlan = {
    name: PlanName;
    ends_at: string | null;
    entitlements: PlanEntitlements;
};

export const PLAN_SETTINGS_HREF = '/settings/plan';

export const FREE_ACTIVE_SERVICE_LIMIT = 3;

export const FREE_PLAN: SharedPlan = {
    name: 'free',
    ends_at: null,
    entitlements: {
        team: false,
        max_active_services: FREE_ACTIVE_SERVICE_LIMIT,
        booking_rules: false,
        calendar_sync: false,
    },
};

export function isCompletePlan(name: PlanName): boolean {
    return name === 'complete';
}

export function planIncludes(feature: PlanFeature, entitlements: PlanEntitlements): boolean {
    return entitlements[feature];
}

export function allowsAnotherActiveService(
    activeCount: number,
    maxActiveServices: number | null,
): boolean {
    if (maxActiveServices === null) {
        return true;
    }

    return activeCount < maxActiveServices;
}
