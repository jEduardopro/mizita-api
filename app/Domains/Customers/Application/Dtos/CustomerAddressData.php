<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;

final readonly class CustomerAddressData
{
    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateId,
        public ?string $stateName,
        public ?string $postalCode,
        public string $countryCode,
    ) {}

    public static function fromSnapshot(CustomerAddressSnapshot $snapshot): self
    {
        return new self(
            street: $snapshot->street,
            city: $snapshot->city,
            stateId: $snapshot->stateId,
            stateName: $snapshot->stateName,
            postalCode: $snapshot->postalCode,
            countryCode: $snapshot->countryCode,
        );
    }
}
