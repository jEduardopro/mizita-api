<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ResolveServiceBookingLinkInput;
use App\Domains\PublicCatalog\Application\Dtos\ServiceBookingLinkTarget;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedServiceLinks;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ResolveServiceBookingLink
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedServiceLinks $serviceLinks,
    ) {}

    /**
     * @return UseCaseResponse<ServiceBookingLinkTarget>
     */
    public function handle(ResolveServiceBookingLinkInput $input): UseCaseResponse
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
     */
    private function resolve(ResolveServiceBookingLinkInput $input): ServiceBookingLinkTarget
    {
        $businessId = $this->businesses->identifyBySlug($input->businessSlug);

        return new ServiceBookingLinkTarget(
            businessSlug: $input->businessSlug,
            serviceId: $this->serviceLinks->bookableServiceIdFor($businessId, $input->serviceSlug),
        );
    }
}
