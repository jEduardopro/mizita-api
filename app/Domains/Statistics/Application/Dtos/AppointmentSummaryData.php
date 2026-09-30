<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\BookingSource;

final readonly class AppointmentSummaryData
{
    /**
     * @param  array<string, int>  $bySource
     */
    public function __construct(
        public int $total,
        public int $attended,
        public int $cancelled,
        public int $upcoming,
        public array $bySource,
    ) {}

    public static function fromTally(AppointmentTally $tally): self
    {
        $bySource = [];

        foreach (BookingSource::cases() as $source) {
            $bySource[$source->value] = $tally->bookedThrough($source);
        }

        return new self($tally->total, $tally->attended, $tally->cancelled, $tally->upcoming, $bySource);
    }
}
