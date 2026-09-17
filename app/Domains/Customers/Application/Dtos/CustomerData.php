<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\ValueObjects\PhoneNumber;
use DateTimeImmutable;

final readonly class CustomerData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?PhoneNumber $phone,
        public ?DateTimeImmutable $birthDate,
        public ?string $notes,
        public ?CustomerAddressData $address,
        public ?string $photoUrl,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(
        Customer $customer,
        ?PhoneNumber $phone,
        ?CustomerAddressSnapshot $address,
        ?string $photoUrl,
    ): self {
        return new self(
            id: $customer->id,
            name: $customer->name(),
            email: $customer->email()?->value,
            phone: $phone,
            birthDate: $customer->birthDate(),
            notes: $customer->notes(),
            address: $address === null ? null : CustomerAddressData::fromSnapshot($address),
            photoUrl: $photoUrl,
            createdAt: $customer->createdAt,
        );
    }
}
