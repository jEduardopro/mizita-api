<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\Dtos;

use App\Domains\Addresses\Entities\Address;

final readonly class AddressData
{
    public function __construct(
        public string $id,
        public string $street,
        public string $city,
        public ?string $stateId,
        public string $postalCode,
        public string $countryCode,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public static function fromEntity(Address $address): self
    {
        $coordinates = $address->coordinates();

        return new self(
            id: $address->id,
            street: $address->street(),
            city: $address->city(),
            stateId: $address->stateId(),
            postalCode: $address->postalCode()->value,
            countryCode: $address->country()->value,
            latitude: $coordinates?->latitude,
            longitude: $coordinates?->longitude,
        );
    }
}
