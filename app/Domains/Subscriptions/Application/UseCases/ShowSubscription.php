<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\ShowSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class ShowSubscription
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<SubscriptionData>
     */
    public function handle(ShowSubscriptionInput $input): UseCaseResponse
    {
        $subscription = $this->subscriptions->forBusiness($input->businessId);

        if ($subscription === null) {
            return UseCaseResponse::success(SubscriptionData::free());
        }

        return UseCaseResponse::success(SubscriptionData::fromSubscription($subscription, $this->clock->now()));
    }
}
