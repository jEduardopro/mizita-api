<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\SubscriptionSyncQueue;
use Throwable;

final class FakeSubscriptionSyncQueue implements SubscriptionSyncQueue
{
    /** @var list<string> */
    public array $scheduled = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
    ) {}

    public function schedule(string $billingSubscriptionId): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->scheduled[] = $billingSubscriptionId;
    }
}
