<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Contracts\PaymentGraceEnforcementQueue;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class EnforcePaymentGrace
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PaymentGraceEnforcementQueue $enforcementQueue,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<int>
     */
    public function handle(): UseCaseResponse
    {
        $scheduled = 0;

        foreach ($this->subscriptions->businessIdsPastPaymentGrace($this->clock->now()) as $businessId) {
            $this->enforcementQueue->schedule($businessId);
            $scheduled++;
        }

        return UseCaseResponse::success($scheduled);
    }
}
