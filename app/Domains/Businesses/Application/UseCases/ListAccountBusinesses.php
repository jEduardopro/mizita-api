<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\UseCases;

use App\Domains\Businesses\Application\Dtos\AccountBusinessData;
use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\ListAccountBusinessesInput;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\MembershipRoles;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\ValueObjects\MembershipRole;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\CurrentBusinessResolver;
use App\Shared\Contracts\DomainFailure;

final class ListAccountBusinesses
{
    public function __construct(
        private readonly BusinessMembership $memberships,
        private readonly BusinessRepository $businesses,
        private readonly BusinessLogo $logo,
        private readonly CurrentBusinessResolver $currentBusiness,
        private readonly MembershipRoles $roles,
    ) {}

    /**
     * @return UseCaseResponse<list<AccountBusinessData>>
     */
    public function handle(ListAccountBusinessesInput $input): UseCaseResponse
    {
        $businessIds = $this->memberships->businessIdsFor($input->accountId);

        if ($businessIds === []) {
            return UseCaseResponse::success([]);
        }

        try {
            $currentBusinessId = $this->currentBusiness->resolveFor($input->accountId, $input->requestedBusinessId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $roles = $this->roles->rolesOf($input->accountId);

        return UseCaseResponse::success(array_map(
            fn (Business $business): AccountBusinessData => new AccountBusinessData(
                business: BusinessData::fromEntity($business, $this->logo->urlFor($business->id)),
                role: $roles[$business->id] ?? MembershipRole::Staff,
                isCurrent: $business->id === $currentBusinessId,
            ),
            $this->businesses->findManyByIds($businessIds),
        ));
    }
}
