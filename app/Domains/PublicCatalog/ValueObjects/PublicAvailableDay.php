<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use DateTimeImmutable;

final readonly class PublicAvailableDay
{
    /**
     * @param  list<DateTimeImmutable>  $starts
     */
    public function __construct(
        public string $date,
        public array $starts,
    ) {}

    public function withoutStarts(): self
    {
        return new self($this->date, []);
    }
}
