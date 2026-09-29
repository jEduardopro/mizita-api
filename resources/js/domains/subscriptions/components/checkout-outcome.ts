import { isCompletePlan } from '@/lib/plan';
import type { Subscription } from '../types';

export function checkoutOutcomeKey(subscription: Subscription) {
    return isCompletePlan(subscription.plan)
        ? ('plan.checkout.subscribed' as const)
        : ('plan.checkout.activationPending' as const);
}
