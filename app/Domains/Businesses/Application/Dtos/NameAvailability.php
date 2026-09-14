<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\NameUnavailabilityReason;

final readonly class NameAvailability
{
    private function __construct(
        public bool $available,
        public ?string $slug,
        public ?NameUnavailabilityReason $reason,
    ) {}

    public static function available(string $slug): self
    {
        return new self(true, $slug, null);
    }

    public static function taken(): self
    {
        return new self(false, null, NameUnavailabilityReason::Taken);
    }

    public static function notSluggable(): self
    {
        return new self(false, null, NameUnavailabilityReason::NotSluggable);
    }
}
