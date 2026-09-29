<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Contracts\SubscriptionSyncQueue;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class ReconcileLapsedSubscriptions
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionSyncQueue $syncQueue,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(): UseCaseResponse
    {
        $scheduled = 0;

        foreach ($this->subscriptions->lapsedWithoutEnding($this->clock->now()) as $subscription) {
            $billingSubscriptionId = $subscription->billingSubscriptionId();

            if ($billingSubscriptionId === null) {
                continue;
            }

            $this->syncQueue->schedule($billingSubscriptionId);
            $scheduled++;
        }

        return UseCaseResponse::success($scheduled);
    }
}
