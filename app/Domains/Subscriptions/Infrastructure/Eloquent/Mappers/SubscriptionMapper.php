<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Eloquent\Mappers;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Infrastructure\Eloquent\Models\SubscriptionModel;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;

final class SubscriptionMapper
{
    public function toEntity(SubscriptionModel $model, string $businessId): Subscription
    {
        return Subscription::restore(
            id: $model->uuid,
            businessId: $businessId,
            billingCustomerId: $model->stripe_customer_id,
            planId: $model->plan->uuid,
            plan: Plan::from($model->plan->key),
            status: SubscriptionStatus::from($model->status),
            billingSubscriptionId: $model->stripe_subscription_id,
            startedAt: $model->started_at,
            currentPeriodEndsAt: $model->current_period_ends_at,
            canceledAt: $model->canceled_at,
            paymentFailedAt: $model->payment_failed_at,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Subscription $subscription, int $businessKey, int $planKey): array
    {
        return [
            'uuid' => $subscription->id,
            'business_id' => $businessKey,
            'plan_id' => $planKey,
            'status' => $subscription->status()->value,
            'stripe_customer_id' => $subscription->billingCustomerId,
            'stripe_subscription_id' => $subscription->billingSubscriptionId(),
            'started_at' => $subscription->startedAt(),
            'current_period_ends_at' => $subscription->currentPeriodEndsAt(),
            'canceled_at' => $subscription->canceledAt(),
            'payment_failed_at' => $subscription->paymentFailedAt(),
        ];
    }
}
