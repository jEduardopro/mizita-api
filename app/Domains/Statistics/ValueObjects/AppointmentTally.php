<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

final readonly class AppointmentTally
{
    /**
     * @param  array<string, int>  $bookedBySource
     */
    public function __construct(
        public int $total,
        public int $attended,
        public int $cancelled,
        public int $upcoming,
        private array $bookedBySource,
    ) {}

    public function bookedThrough(BookingSource $source): int
    {
        return $this->bookedBySource[$source->value] ?? 0;
    }
}
