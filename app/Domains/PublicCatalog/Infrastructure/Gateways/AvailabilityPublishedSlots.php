<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Application\Services\AvailabilityBoard;
use App\Domains\PublicCatalog\Contracts\PublishedSlots;
use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Domains\PublicCatalog\ValueObjects\PublicSlotQuery;

final class AvailabilityPublishedSlots implements PublishedSlots
{
    public function __construct(
        private readonly AvailabilityBoard $board,
    ) {}

    /**
     * @return list<PublicAvailableDay>
     */
    public function forBusiness(string $businessId, PublicSlotQuery $query): array
    {
        $days = $this->board->forBusiness($businessId, new SlotQuery(
            serviceId: $query->serviceId,
            staffId: $query->staffId,
            from: $query->from,
            to: $query->to,
        ));

        return array_map(
            static fn (AvailableDayData $day): PublicAvailableDay => new PublicAvailableDay(
                date: $day->date,
                starts: $day->starts,
            ),
            $days,
        );
    }
}
