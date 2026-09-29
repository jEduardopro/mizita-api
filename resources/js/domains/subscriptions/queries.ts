import { router } from '@inertiajs/react';
import {
    queryOptions,
    useIsMutating,
    useMutation,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import { useCallback } from 'react';
import { isCompletePlan } from '@/lib/plan';
import {
    confirmCheckoutSession,
    createBillingPortalSession,
    createCheckoutSession,
    getSubscription,
    listPlans,
    resumeSubscription,
    switchSubscriptionToFree,
} from './api';
import type { CheckoutSession, Plan, PlanKey, Subscription } from './types';

const PLANS_LIFETIME_MS = 5 * 60 * 1000;

const ACTIVATION_POLL_INTERVAL_MS = 1500;

const ACTIVATION_POLL_ATTEMPTS = 8;

const CHECKOUT_PLAN_KEY: PlanKey = 'complete';

const SHARED_PLAN_PROP = 'plan';

export const subscriptionKeys = {
    all: ['subscriptions'] as const,
    current: () => [...subscriptionKeys.all, 'current'] as const,
    plans: () => [...subscriptionKeys.all, 'plans'] as const,
    payment: () => [...subscriptionKeys.all, 'payment'] as const,
};

export type CheckoutStart = {
    plan: Plan;
    session: CheckoutSession;
};

export type PaymentConfirmation = () => Promise<void>;

type CheckoutPayment = {
    sessionId: string;
    confirmPayment: PaymentConfirmation;
};

export class CheckoutPaymentDeclined extends Error {}

export class CheckoutPlanUnavailable extends Error {}

function plansQuery() {
    return queryOptions({
        queryKey: subscriptionKeys.plans(),
        queryFn: ({ signal }) => listPlans(signal),
        staleTime: PLANS_LIFETIME_MS,
    });
}

function checkoutPlanOf(plans: Plan[]): Plan {
    const plan = plans.find((candidate) => candidate.key === CHECKOUT_PLAN_KEY);

    if (plan === undefined) {
        throw new CheckoutPlanUnavailable();
    }

    return plan;
}

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => {
        window.setTimeout(resolve, milliseconds);
    });
}

async function awaitCompletePlan(confirmed: Subscription): Promise<Subscription> {
    let latest = confirmed;

    for (
        let attempt = 0;
        attempt < ACTIVATION_POLL_ATTEMPTS && ! isCompletePlan(latest.plan);
        attempt += 1
    ) {
        await wait(ACTIVATION_POLL_INTERVAL_MS);
        latest = await getSubscription();
    }

    return latest;
}

async function confirmAndAwaitActivation(sessionId: string): Promise<Subscription> {
    return awaitCompletePlan(await confirmCheckoutSession(sessionId));
}

function useSubscriptionRefresh() {
    const queryClient = useQueryClient();

    return useCallback(
        (subscription: Subscription) => {
            queryClient.setQueryData(subscriptionKeys.current(), subscription);
            router.reload({ only: [SHARED_PLAN_PROP] });
        },
        [queryClient],
    );
}

export function useSubscription() {
    return useQuery({
        queryKey: subscriptionKeys.current(),
        queryFn: ({ signal }) => getSubscription(signal),
    });
}

export function useStartCheckout() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (): Promise<CheckoutStart> => {
            const plan = checkoutPlanOf(await queryClient.ensureQueryData(plansQuery()));
            const session = await createCheckoutSession({ plan_id: plan.id });

            return { plan, session };
        },
    });
}

export function usePayCheckout() {
    const refreshSubscription = useSubscriptionRefresh();

    return useMutation({
        mutationKey: subscriptionKeys.payment(),
        mutationFn: async ({ sessionId, confirmPayment }: CheckoutPayment) => {
            await confirmPayment();

            return confirmAndAwaitActivation(sessionId);
        },
        onSuccess: refreshSubscription,
    });
}

export function useIsPayingCheckout(): boolean {
    return useIsMutating({ mutationKey: subscriptionKeys.payment() }) > 0;
}

export function useConfirmCheckout() {
    const refreshSubscription = useSubscriptionRefresh();

    return useMutation({
        mutationFn: confirmAndAwaitActivation,
        onSuccess: refreshSubscription,
    });
}

export function useSwitchToFreePlan() {
    const refreshSubscription = useSubscriptionRefresh();

    return useMutation({
        mutationFn: switchSubscriptionToFree,
        onSuccess: refreshSubscription,
    });
}

export function useResumeSubscription() {
    const refreshSubscription = useSubscriptionRefresh();

    return useMutation({
        mutationFn: resumeSubscription,
        onSuccess: refreshSubscription,
    });
}

export function useOpenBillingPortal() {
    return useMutation({
        mutationFn: createBillingPortalSession,
    });
}
