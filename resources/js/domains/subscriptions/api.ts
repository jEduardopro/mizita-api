import { api } from '@/lib/api';
import type {
    BillingPortalSession,
    CheckoutSession,
    Plan,
    StartCheckoutPayload,
    Subscription,
} from './types';

export async function getSubscription(signal?: AbortSignal): Promise<Subscription> {
    const { data } = await api.get<{ data: Subscription }>('/subscription', { signal });

    return data.data;
}

export async function listPlans(signal?: AbortSignal): Promise<Plan[]> {
    const { data } = await api.get<{ data: Plan[] }>('/plans', { signal });

    return data.data;
}

export async function createCheckoutSession(payload: StartCheckoutPayload): Promise<CheckoutSession> {
    const { data } = await api.post<{ data: CheckoutSession }>('/subscription/checkout', payload);

    return data.data;
}

export async function confirmCheckoutSession(sessionId: string): Promise<Subscription> {
    const { data } = await api.post<{ data: Subscription }>(
        `/subscription/checkout/${encodeURIComponent(sessionId)}/confirm`,
    );

    return data.data;
}

export async function switchSubscriptionToFree(): Promise<Subscription> {
    const { data } = await api.post<{ data: Subscription }>('/subscription/switch-to-free');

    return data.data;
}

export async function resumeSubscription(): Promise<Subscription> {
    const { data } = await api.post<{ data: Subscription }>('/subscription/resume');

    return data.data;
}

export async function createBillingPortalSession(): Promise<BillingPortalSession> {
    const { data } = await api.post<{ data: BillingPortalSession }>('/subscription/billing-portal');

    return data.data;
}
