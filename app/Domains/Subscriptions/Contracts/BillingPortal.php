<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

interface BillingPortal
{
    public function urlFor(string $billingCustomerId, string $returnUrl): string;
}
