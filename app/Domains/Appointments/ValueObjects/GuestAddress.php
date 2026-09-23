<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

final readonly class GuestAddress
{
    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateName,
        public ?string $postalCode,
        public string $countryCode,
    ) {}
}
