<?php

declare(strict_types=1);

namespace App\Domains\Industries\Entities;

use App\Domains\Industries\Exceptions\IndustryAlreadyActive;
use App\Domains\Industries\Exceptions\IndustryAlreadyInactive;
use App\Domains\Industries\Exceptions\InvalidIndustryKey;
use DateTimeImmutable;

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
