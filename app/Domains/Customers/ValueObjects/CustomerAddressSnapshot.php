<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

final readonly class CustomerAddressSnapshot
{
    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateId,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $stateName = null,
    ) {}
}
