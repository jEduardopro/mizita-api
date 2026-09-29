<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use Stripe\Subscription;

final class StripeBillingSubscriptions implements BillingSubscriptions
{
    public function __construct(
        private readonly StripeApi $stripe,
        private readonly StripeSnapshotMapper $snapshots,
    ) {}

    public function fetch(string $subscriptionId): BillingSnapshot
    {
        return $this->snapshots->toSnapshot($this->stripe->retrieveSubscription($subscriptionId));
    }

    public function cancelAtPeriodEnd(string $subscriptionId): BillingSnapshot
    {
        return $this->snapshots->toSnapshot(
            $this->stripe->updateSubscription($subscriptionId, ['cancel_at_period_end' => true]),
        );
    }

    public function resume(string $subscriptionId): BillingSnapshot
    {
        return $this->snapshots->toSnapshot(
            $this->stripe->updateSubscription($subscriptionId, ['cancel_at_period_end' => false]),
        );
    }

    public function cancelNow(string $subscriptionId): BillingSnapshot
    {
        $subscription = $this->stripe->retrieveSubscription($subscriptionId);

        if ($subscription->status === Subscription::STATUS_CANCELED) {
            return $this->snapshots->toSnapshot($subscription);
        }

        return $this->snapshots->toSnapshot($this->stripe->cancelSubscription($subscriptionId));
    }
}
