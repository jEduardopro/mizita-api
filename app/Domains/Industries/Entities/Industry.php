<?php

declare(strict_types=1);

namespace App\Domains\Industries\Entities;

use App\Domains\Industries\Exceptions\IndustryAlreadyActive;
use App\Domains\Industries\Exceptions\IndustryAlreadyInactive;
use App\Domains\Industries\Exceptions\InvalidIndustryKey;
use DateTimeImmutable;

/**
 * A row of the industry catalog: the kind of work a business does.
 *
 * Plain PHP, no framework. The catalog is seeded rather than created over
 * HTTP, so restore() carries most of the traffic; create() exists for the
 * seeder and enforces the one creation-time rule the key has.
 */
final class Industry
{
    private function __construct(
        public readonly string $id,
        private string $key,
        private int $position,
        private bool $active,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws InvalidIndustryKey
     */
    public static function create(
        string $id,
        string $key,
        int $position,
        DateTimeImmutable $now,
    ): self {
        $key = trim($key);

        if ($key === '') {
            throw InvalidIndustryKey::empty();
        }

        return new self(
            id: $id,
            key: $key,
            position: $position,
            active: true,
            createdAt: $now,
        );
    }

    /**
     * Rehydrates an industry from storage. Skips creation-time rules by
     * design: the data was already valid when it was written.
     */
    public static function restore(
        string $id,
        string $key,
        int $position,
        bool $active,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            key: $key,
            position: $position,
            active: $active,
            createdAt: $createdAt,
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Puts the industry back in the catalog.
     *
     * @throws IndustryAlreadyActive
     */
    public function activate(): void
    {
        if ($this->active) {
            throw IndustryAlreadyActive::for($this->id);
        }

        $this->active = true;
    }

    /**
     * Retires the industry from the catalog while keeping it resolvable for
     * the businesses already pointing at it. That is business state, which is
     * why it is not a soft delete on the record.
     *
     * @throws IndustryAlreadyInactive
     */
    public function deactivate(): void
    {
        if (! $this->active) {
            throw IndustryAlreadyInactive::for($this->id);
        }

        $this->active = false;
    }
}
