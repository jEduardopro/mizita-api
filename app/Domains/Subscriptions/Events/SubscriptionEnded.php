<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Events;

final readonly class SubscriptionEnded
{
    public function __construct(
        public string $subscriptionId,
        public string $businessId,
    ) {}
}
