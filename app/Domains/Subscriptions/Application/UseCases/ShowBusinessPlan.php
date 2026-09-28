<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\BusinessPlanData;
use App\Domains\Subscriptions\Application\Dtos\ShowBusinessPlanInput;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;

final class ShowBusinessPlan
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<BusinessPlanData>
     */
    public function handle(ShowBusinessPlanInput $input): UseCaseResponse
    {
        $subscription = $this->subscriptions->inEffectFor($input->businessId, $this->clock->now());

        if ($subscription === null) {
            return UseCaseResponse::success(BusinessPlanData::free());
        }

        return UseCaseResponse::success(BusinessPlanData::fromSubscription($subscription));
    }
}
