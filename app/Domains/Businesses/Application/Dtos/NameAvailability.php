<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\NameUnavailabilityReason;

/**
 * The answer to "can I call my business this?", as the signup form asks it
 * while somebody types.
 *
 * An answer, never an exception: the question is expected to come back no, and
 * a failure the caller asked for is not a failure. That is also why the slug is
 * here - a person choosing a name deserves to see the address it will give them
 * before they commit to it.
 */
final readonly class NameAvailability
{
    private function __construct(
        public bool $available,
        /** The address the name would get, when it is free. */
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
