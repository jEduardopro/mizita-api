<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

final readonly class ScheduleInterval
{
    public function __construct(
        public Weekday $weekday,
        public TimeOfDay $startsAt,
        public TimeOfDay $endsAt,
    ) {}
}
