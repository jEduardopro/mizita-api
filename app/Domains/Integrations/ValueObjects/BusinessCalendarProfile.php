<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use DateTimeZone;

final readonly class BusinessCalendarProfile
{
    private const CALENDAR_NAME_PREFIX = 'Mizita – ';

    public function __construct(
        public string $name,
        public string $timezone,
    ) {}

    public function calendarName(): string
    {
        return self::CALENDAR_NAME_PREFIX.$this->name;
    }

    public function localZone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }
}
