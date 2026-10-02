<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Queue;

use App\Domains\Subscriptions\Contracts\PaymentGraceEnforcementQueue;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueuedPaymentGraceEnforcement implements PaymentGraceEnforcementQueue
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function schedule(string $businessId): void
    {
        $this->bus->dispatch(new CancelSubscriptionInStripe($businessId));
    }
}
