<?php

declare(strict_types=1);

namespace App\Domains\Addresses\ValueObjects;

use App\Domains\Addresses\Entities\State;
use App\Shared\ValueObjects\CountryCode;

final readonly class AddressRegion
{
    private function __construct(
        public ?string $stateId,
        public ?string $stateName,
        public CountryCode $country,
    ) {}

    public static function chosen(string $stateId, CountryCode $country): self
    {
        return new self(stateId: $stateId, stateName: null, country: $country);
    }

    public static function catalogued(State $state): self
    {
        return new self(stateId: $state->id, stateName: null, country: $state->country());
    }

    public static function typed(?string $stateName, CountryCode $country): self
    {
        return new self(stateId: null, stateName: $stateName, country: $country);
    }
}
