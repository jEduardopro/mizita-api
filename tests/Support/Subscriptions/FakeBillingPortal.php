<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BillingPortal;

final class FakeBillingPortal implements BillingPortal
{
    public const URL_PREFIX = 'https://billing.stripe.test/p/session/';

    /** @var list<array{billingCustomerId: string, returnUrl: string}> */
    public array $requests = [];

    public function urlFor(string $billingCustomerId, string $returnUrl): string
    {
        $this->requests[] = ['billingCustomerId' => $billingCustomerId, 'returnUrl' => $returnUrl];

        return self::URL_PREFIX.$billingCustomerId;
    }
}
