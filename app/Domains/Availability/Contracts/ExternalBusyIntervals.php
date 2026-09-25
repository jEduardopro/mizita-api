<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\ValueObjects\BookedInterval;
use DateTimeImmutable;

interface ExternalBusyIntervals
{
    /**
     * @return list<BookedInterval>
     */
    public function forStaffBetween(
        string $businessId,
        string $staffId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;
}
