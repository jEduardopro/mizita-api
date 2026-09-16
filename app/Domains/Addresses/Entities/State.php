<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Entities;

use App\Shared\ValueObjects\CountryCode;

final class State
{
    private function __construct(
        public readonly string $id,
        private CountryCode $country,
        private string $code,
        private string $name,
        private int $position,
        private bool $active,
    ) {}

    public static function restore(
        string $id,
        CountryCode $country,
        string $code,
        string $name,
        int $position,
        bool $active,
    ): self {
        return new self(
            id: $id,
            country: $country,
            code: $code,
            name: $name,
            position: $position,
            active: $active,
        );
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
