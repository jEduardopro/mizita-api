<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicAvailabilityInput;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedSlots;
use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowPublicAvailability
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedSlots $slots,
    ) {}

    /**
     * @return UseCaseResponse<list<PublicAvailableDay>>
     */
    public function handle(ShowPublicAvailabilityInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->businesses->identifyBySlug($input->slug);

            return UseCaseResponse::success($this->slots->forBusiness($businessId, $input->query));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
