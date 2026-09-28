<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ResolveStaffBookingLinkInput;
use App\Domains\PublicCatalog\Application\Dtos\StaffBookingLinkTarget;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedStaffLinks;
use App\Domains\PublicCatalog\Contracts\PublishedStaffServices;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ResolveStaffBookingLink
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedStaffLinks $staffLinks,
        private readonly PublishedStaffServices $staffServices,
    ) {}

    /**
     * @return UseCaseResponse<StaffBookingLinkTarget>
     */
    public function handle(ResolveStaffBookingLinkInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            return UseCaseResponse::success($this->resolve($input));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessPageNotFound
     * @throws StaffBookingPageNotFound
     */
    private function resolve(ResolveStaffBookingLinkInput $input): StaffBookingLinkTarget
    {
        $businessId = $this->businesses->identifyBySlug($input->businessSlug);
        $staffMemberId = $this->staffLinks->staffMemberIdFor($businessId, $input->staffSlug);

        return new StaffBookingLinkTarget(
            businessSlug: $input->businessSlug,
            staffMemberId: $staffMemberId,
            serviceId: $this->offeredServiceId($businessId, $staffMemberId, $input->serviceSlug),
        );
    }

    private function offeredServiceId(string $businessId, string $staffMemberId, ?string $serviceSlug): ?string
    {
        if ($serviceSlug === null) {
            return null;
        }

        return $this->staffServices->offeredServiceIdFor($businessId, $staffMemberId, $serviceSlug);
    }
}
