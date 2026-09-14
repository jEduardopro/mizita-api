<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Entities;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use DateTimeImmutable;

/**
 * The tenant root. Every other record on the platform belongs to exactly one of
 * these, which is why this entity carries no businessId of its own.
 *
 * Slug and Timezone are held as value objects and handed out as strings: the
 * rules travel with the entity, while callers keep dealing in the primitives a
 * DTO and a resource are made of.
 */
final class Business
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private Slug $slug,
        /** The industry's uuid. Resolving it to a foreign key is the adapter's job. */
        public readonly string $industryId,
        private Timezone $timezone,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws InvalidBusinessName
     */
    public static function create(
        string $id,
        string $name,
        Slug $slug,
        string $industryId,
        Timezone $timezone,
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
            industryId: $industryId,
            timezone: $timezone,
            createdAt: $now,
        );
    }

    /** Skips creation-time rules by design: the data was already valid when written. */
    public static function restore(
        string $id,
        string $name,
        Slug $slug,
        string $industryId,
        Timezone $timezone,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            slug: $slug,
            industryId: $industryId,
            timezone: $timezone,
            createdAt: $createdAt,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug->value;
    }

    public function timezone(): string
    {
        return $this->timezone->value;
    }
}
