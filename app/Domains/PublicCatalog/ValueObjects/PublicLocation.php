<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicLocation
{
    public function __construct(
        public string $street,
        public string $city,
        public ?string $state,
        public string $postalCode,
        public string $countryCode,
        public ?string $latitude,
        public ?string $longitude,
    ) {}
}
