<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Availability\Application\Services\BusinessOpeningHours;
use App\Domains\Availability\ValueObjects\OpenState;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;

final class AvailabilityPublishedOpenState implements PublishedOpenState
{
    public function __construct(
        private readonly BusinessOpeningHours $openingHours,
    ) {}

    public function forBusiness(string $businessId): PublicOpenState
    {
        return self::describe($this->openingHours->stateOf($businessId));
    }

    private static function describe(OpenState $state): PublicOpenState
    {
        $closesAt = $state->closesAt;

        if ($closesAt !== null) {
            return PublicOpenState::openUntil($closesAt->toString());
        }

        $opensOn = $state->opensOn;
        $opensAt = $state->opensAt;

        if ($opensOn === null || $opensAt === null) {
            return PublicOpenState::closedIndefinitely();
        }

        return PublicOpenState::closedUntil($opensOn->value, $opensAt->toString());
    }
}
