<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

final readonly class SyncSubscriptionFromBillingInput
{
    public function __construct(
        public string $billingSubscriptionId,
    ) {}
}
