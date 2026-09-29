import type { PlanName } from '@/lib/plan';

export type SubscriptionStatus =
    | 'incomplete'
    | 'trialing'
    | 'active'
    | 'past_due'
    | 'canceled'
    | 'unpaid'
    | 'incomplete_expired'
    | 'paused';

export type Subscription = {
    id: string | null;
    plan: PlanName;
    status: SubscriptionStatus | null;
    started_at: string | null;
    current_period_ends_at: string | null;
    canceled_at: string | null;
    payment_grace_ends_at: string | null;
    can_checkout: boolean;
    can_switch_to_free: boolean;
    can_resume: boolean;
    can_manage_billing: boolean;
};

export type PlanKey = 'complete';

export type PlanInterval = 'month';

export type Plan = {
    id: string;
    key: PlanKey;
    name: string;
    price_cents: number;
    currency_code: string;
    interval: PlanInterval;
    trial_days: number | null;
};

export type CheckoutSession = {
    session_id: string;
    client_secret: string;
};

export type StartCheckoutPayload = {
    plan_id: string;
};

export type BillingPortalSession = {
    url: string;
};
