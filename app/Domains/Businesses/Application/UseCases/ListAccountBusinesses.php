<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\ListAccountBusinessesInput;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Entities\Business;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessMembership;

final class ListAccountBusinesses
{
    public function __construct(
        private readonly BusinessMembership $memberships,
        private readonly BusinessRepository $businesses,
    ) {}

    /**
     * @return UseCaseResponse<list<BusinessData>>
     */
    public function handle(ListAccountBusinessesInput $input): UseCaseResponse
    {
        $businessIds = $this->memberships->businessIdsFor($input->accountId);

        return UseCaseResponse::success(array_map(
            static fn (Business $business): BusinessData => BusinessData::fromEntity($business),
            $this->businesses->findManyByIds($businessIds),
        ));
    }
}
