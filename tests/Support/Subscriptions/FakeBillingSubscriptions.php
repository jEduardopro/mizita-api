<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use RuntimeException;

final class FakeBillingSubscriptions implements BillingSubscriptions
{
    /** @var array<string, BillingSnapshot> */
    private array $snapshots = [];

    /** @var list<array{operation: string, subscriptionId: string}> */
    public array $calls = [];

    public function __construct(BillingSnapshot ...$snapshots)
    {
        foreach ($snapshots as $snapshot) {
            $this->snapshots[$snapshot->subscriptionId] = $snapshot;
        }
    }

    public function fetch(string $subscriptionId): BillingSnapshot
    {
        return $this->answer(__FUNCTION__, $subscriptionId);
    }

    public function cancelAtPeriodEnd(string $subscriptionId): BillingSnapshot
    {
        return $this->answer(__FUNCTION__, $subscriptionId);
    }

    public function resume(string $subscriptionId): BillingSnapshot
    {
        return $this->answer(__FUNCTION__, $subscriptionId);
    }

    public function cancelNow(string $subscriptionId): BillingSnapshot
    {
        return $this->answer(__FUNCTION__, $subscriptionId);
    }

    private function answer(string $operation, string $subscriptionId): BillingSnapshot
    {
        $this->calls[] = ['operation' => $operation, 'subscriptionId' => $subscriptionId];

        return $this->snapshots[$subscriptionId]
            ?? throw new RuntimeException("FakeBillingSubscriptions knows no subscription [{$subscriptionId}].");
    }
}
