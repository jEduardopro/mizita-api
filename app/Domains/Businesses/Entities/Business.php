<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Entities;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use DateTimeImmutable;

/**
 * Domain entity: plain PHP, no framework. It owns the business rules and
 * protects its own invariants. Persistence is handled by the repository
 * adapter through BusinessMapper.
 */
final class Business
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private string $slug,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * Creates a brand new business. Enforces creation-time rules.
     */
    public static function create(
        string $id,
        string $name,
        string $slug,
        DateTimeImmutable $now,
    ): self {
        $name = trim($name);

        if ($name === '') {
            throw InvalidBusinessName::empty();
        }

        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            createdAt: $now,
        );
    }

    /**
     * Rehydrates a business from storage. Skips creation-time rules by
     * design: the data was already valid when it was written.
     */
    public static function restore(
        string $id,
        string $name,
        string $slug,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            createdAt: $createdAt,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
