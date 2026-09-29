<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\PlanData;
use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\ValueObjects\PlanOffer;
use App\Shared\Application\UseCaseResponse;

final class ListPlans
{
    public function __construct(
        private readonly PlanCatalog $plans,
    ) {}

    /**
     * @return UseCaseResponse<list<PlanData>>
     */
    public function handle(): UseCaseResponse
    {
        return UseCaseResponse::success(array_map(
            static fn (PlanOffer $offer): PlanData => PlanData::fromOffer($offer),
            $this->plans->all(),
        ));
    }
}
