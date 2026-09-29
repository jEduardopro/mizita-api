<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Queue;

use App\Domains\Subscriptions\Contracts\SubscriptionSyncQueue;
use Illuminate\Contracts\Bus\Dispatcher;

final class QueuedSubscriptionSync implements SubscriptionSyncQueue
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function schedule(string $billingSubscriptionId): void
    {
        $this->bus->dispatch(new SyncSubscriptionFromStripe($billingSubscriptionId));
    }
}
