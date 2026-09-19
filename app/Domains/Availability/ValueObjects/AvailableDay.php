<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use DateTimeImmutable;

final readonly class AvailableDay
{
    /**
     * @param  list<DateTimeImmutable>  $starts
     */
    public function __construct(
        public string $date,
        public array $starts,
    ) {}
}
