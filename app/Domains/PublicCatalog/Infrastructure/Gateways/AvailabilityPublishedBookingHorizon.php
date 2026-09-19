<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Availability\Application\Services\BusinessBookingHorizon;
use App\Domains\PublicCatalog\Contracts\PublishedBookingHorizon;

final class AvailabilityPublishedBookingHorizon implements PublishedBookingHorizon
{
    public function __construct(
        private readonly BusinessBookingHorizon $horizon,
    ) {}

    public function lastBookableDateFor(string $businessId): string
    {
        return $this->horizon->lastBookableDateOf($businessId);
    }
}
