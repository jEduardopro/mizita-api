<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicCancellationWindow
{
    private function __construct(
        public ?int $minutes,
    ) {}

    public static function ofMinutes(int $minutes): self
    {
        return new self($minutes);
    }

    public static function notAllowed(): self
    {
        return new self(null);
    }
}
