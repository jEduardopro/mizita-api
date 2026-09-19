<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\UseCases;

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicAvailabilityInput;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\Contracts\PublishedSlots;
use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Domains\PublicCatalog\ValueObjects\PublicSlotQuery;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;

final class ShowPublicAvailability
{
    public function __construct(
        private readonly PublishedBusinesses $businesses,
        private readonly PublishedSlots $slots,
        private readonly PublishedOpenState $openState,
    ) {}

    /**
     * @return UseCaseResponse<list<PublicAvailableDay>>
     */
    public function handle(ShowPublicAvailabilityInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->businesses->identifyBySlug($input->slug);

            return UseCaseResponse::success($this->offeredBy($businessId, $input->query));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @return list<PublicAvailableDay>
     */
    private function offeredBy(string $businessId, PublicSlotQuery $query): array
    {
        $days = $this->slots->forBusiness($businessId, $query);

        if ($this->openState->forBusiness($businessId)->isOpen()) {
            return $days;
        }

        return array_map(
            static fn (PublicAvailableDay $day): PublicAvailableDay => $day->withoutStarts(),
            $days,
        );
    }
}
