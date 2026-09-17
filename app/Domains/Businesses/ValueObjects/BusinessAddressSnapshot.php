<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BusinessAddressSnapshot
{
    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateId,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $latitude,
        public ?string $longitude,
    ) {}
}
