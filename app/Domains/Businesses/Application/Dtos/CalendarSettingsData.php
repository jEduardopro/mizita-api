<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

final readonly class CalendarSettingsData
{
    /**
     * @param  list<BusinessScheduleEntry>  $schedule
     */
    public function __construct(
        public string $timezone,
        public string $currencyCode,
        public array $schedule,
    ) {}
}
