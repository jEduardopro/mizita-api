<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\Dtos;

use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;

final readonly class ReplaceAddressInput
{
    public function __construct(
        public AddressOwnerType $ownerType,
        public string $ownerId,
        public string $street,
        public ?string $city,
        public ?string $stateId,
        public ?PostalCode $postalCode,
        public CountryCode $country,
        public ?Coordinates $coordinates,
    ) {}
}
