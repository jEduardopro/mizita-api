<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use App\Domains\Subscriptions\Contracts\BillingPortal;

final class StripeBillingPortal implements BillingPortal
{
    public function __construct(
        private readonly StripeApi $stripe,
    ) {}

    public function urlFor(string $billingCustomerId, string $returnUrl): string
    {
        return $this->stripe->createPortalSession([
            'customer' => $billingCustomerId,
            'return_url' => $returnUrl,
        ])->url;
    }
}
