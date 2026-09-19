<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use DateTimeImmutable;

final readonly class BookedInterval
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}

    public function overlaps(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $from < $this->endsAt && $this->startsAt < $to;
    }
}
