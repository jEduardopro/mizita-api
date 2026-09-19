<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\OpeningHours;
use App\Domains\Availability\Application\Services\BusinessOpeningHours;

final class AvailabilityOpeningHours implements OpeningHours
{
    public function __construct(
        private readonly BusinessOpeningHours $openingHours,
    ) {}

    public function isOpenNow(string $businessId): bool
    {
        return $this->openingHours->stateOf($businessId)->isOpen();
    }
}
