<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

use DateTimeImmutable;

final readonly class ReportingWindow
{
    public function __construct(
        public LocalDate $from,
        public LocalDate $to,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}

    /**
     * @return list<LocalDate>
     */
    public function dates(): array
    {
        $dates = [];
        $cursor = $this->from;

        while (! $cursor->isAfter($this->to)) {
            $dates[] = $cursor;
            $cursor = $cursor->plusDays(1);
        }

        return $dates;
    }
}
