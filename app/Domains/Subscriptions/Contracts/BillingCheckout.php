<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\CheckoutRequest;
use App\Domains\Subscriptions\ValueObjects\CheckoutSession;

interface BillingCheckout
{
    public function start(CheckoutRequest $request): CheckoutSession;

    /**
     * @throws CheckoutSessionNotFound
     */
    public function subscriptionOf(string $sessionId, string $billingCustomerId): ?BillingSnapshot;
}
