<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BusinessScheduleEntry
{
    public function __construct(
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
    ) {}
}
