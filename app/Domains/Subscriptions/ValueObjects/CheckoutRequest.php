<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $businessId,
        public string $billingCustomerId,
        public string $billingPriceId,
        public ?int $trialDays,
        public string $returnUrl,
    ) {}
}
