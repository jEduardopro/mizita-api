<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

final readonly class PlanOffer
{
    public function __construct(
        public string $id,
        public Plan $key,
        public string $name,
        public SubscriptionPrice $price,
        public BillingInterval $interval,
        public ?int $trialDays,
        public string $billingPriceId,
    ) {}
}
