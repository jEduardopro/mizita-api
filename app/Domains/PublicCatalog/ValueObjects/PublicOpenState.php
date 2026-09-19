<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicOpenState
{
    private function __construct(
        public ?string $closesAt,
        public ?int $opensOnWeekday,
        public ?string $opensAt,
    ) {}

    public static function openUntil(string $closesAt): self
    {
        return new self($closesAt, null, null);
    }

    public static function closedUntil(int $opensOnWeekday, string $opensAt): self
    {
        return new self(null, $opensOnWeekday, $opensAt);
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
