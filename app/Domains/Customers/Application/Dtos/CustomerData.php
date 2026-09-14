<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Entities\Customer;
use DateTimeImmutable;

final readonly class CustomerData
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            businessId: $customer->businessId,
            name: $customer->name(),
            email: $customer->email(),
            phone: $customer->phone(),
            createdAt: $customer->createdAt,
        );
    }
}
