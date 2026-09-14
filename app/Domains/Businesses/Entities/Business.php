<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Entities;

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\ValueObjects\Slug;
use App\Domains\Businesses\ValueObjects\Timezone;
use DateTimeImmutable;

final class Business
{
    private function __construct(
        public readonly string $id,
        private string $name,
        private Slug $slug,
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
