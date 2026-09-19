<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\ValueObjects\AvailableDay;
use DateTimeImmutable;

final readonly class AvailableDayData
{
    /**
     * @param  list<DateTimeImmutable>  $starts
     */
    public function __construct(
        public string $date,
        public array $starts,
    ) {}

    public static function fromDay(AvailableDay $day): self
    {
        return new self(
            date: $day->date,
            starts: $day->starts,
        );
    }
}
