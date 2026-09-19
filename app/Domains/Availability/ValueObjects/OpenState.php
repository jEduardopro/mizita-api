<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

final readonly class OpenState
{
    private function __construct(
        public ?TimeOfDay $closesAt,
        public ?Weekday $opensOn,
        public ?TimeOfDay $opensAt,
    ) {}

    public static function openUntil(TimeOfDay $closesAt): self
    {
        return new self($closesAt, null, null);
    }

    public static function closedUntil(Weekday $opensOn, TimeOfDay $opensAt): self
    {
        return new self(null, $opensOn, $opensAt);
    }

    public static function closedIndefinitely(): self
    {
        return new self(null, null, null);
    }

    public function isOpen(): bool
    {
        return $this->closesAt !== null;
    }
}
