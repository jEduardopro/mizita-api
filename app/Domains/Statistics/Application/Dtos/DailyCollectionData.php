<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

use App\Domains\Statistics\ValueObjects\LocalDate;

final readonly class DailyCollectionData
{
    public function __construct(
        public string $date,
        public int $collectedCents,
    ) {}

    public static function of(LocalDate $date, int $collectedCents): self
    {
        return new self($date->toString(), $collectedCents);
    }
}
