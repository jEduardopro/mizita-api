<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;

interface BillingSubscriptions
{
    public function fetch(string $subscriptionId): BillingSnapshot;

    public function cancelAtPeriodEnd(string $subscriptionId): BillingSnapshot;

    public function resume(string $subscriptionId): BillingSnapshot;

    public function cancelNow(string $subscriptionId): BillingSnapshot;
}
