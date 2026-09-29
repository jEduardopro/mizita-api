import { isCompletePlan } from '@/lib/plan';
import type { Subscription } from '../types';

export type SubscriptionStanding =
    | { kind: 'free' }
    | { kind: 'renewing'; renewsAt: string }
    | { kind: 'ending'; accessEndsAt: string };

const PAST_DUE_STATUS = 'past_due';

export function standingOf(subscription: Subscription): SubscriptionStanding {
    const periodEndsAt = subscription.current_period_ends_at;

    if (periodEndsAt === null || ! isCompletePlan(subscription.plan)) {
        return { kind: 'free' };
    }

    if (subscription.can_resume) {
        return { kind: 'ending', accessEndsAt: periodEndsAt };
    }

    return { kind: 'renewing', renewsAt: periodEndsAt };
}

export function paymentGraceDeadlineOf(subscription: Subscription): string | null {
    if (subscription.status !== PAST_DUE_STATUS) {
        return null;
    }

    return subscription.payment_grace_ends_at;
}
