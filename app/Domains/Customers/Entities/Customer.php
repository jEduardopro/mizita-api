<?php

declare(strict_types=1);

namespace App\Domains\Customers\Entities;

use App\Domains\Customers\Exceptions\InvalidCustomerName;
use DateTimeImmutable;

/**
 * Domain entity: plain PHP, no framework. It owns the business rules and
 * protects its own invariants. Persistence is handled by the repository
 * adapter through CustomerMapper.
 */
final class Customer
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private string $name,
        private ?string $email,
        private ?string $phone,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Creates a brand new customer. Enforces creation-time rules.
     */
    public static function create(
        string $id,
        string $businessId,
        string $name,
        ?string $email,
        ?string $phone,
        DateTimeImmutable $now,
    ): self {
        $name = trim($name);

        if ($name === '') {
            throw InvalidCustomerName::empty();
        }

        return new self(
            id: $id,
            businessId: $businessId,
            name: $name,
            email: $email,
            phone: $phone,
            createdAt: $now,
        );
    }

    /**
     * Rehydrates a customer from storage. Skips creation-time rules by
     * design: the data was already valid when it was written.
     */
    public static function restore(
        string $id,
        string $businessId,
        string $name,
        ?string $email,
        ?string $phone,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            name: $name,
            email: $email,
            phone: $phone,
            createdAt: $createdAt,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }
}
