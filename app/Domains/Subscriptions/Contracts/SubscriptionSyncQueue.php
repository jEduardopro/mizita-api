<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

interface SubscriptionSyncQueue
{
    public function schedule(string $billingSubscriptionId): void;
}
