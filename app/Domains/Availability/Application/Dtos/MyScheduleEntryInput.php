<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;

final readonly class MyScheduleEntryInput
{
    private const HOURS_AND_MINUTES_SHAPE = '/^(?:[01]\d|2[0-3]):[0-5]\d$/D';

    public function __construct(
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
    ) {}

    /**
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     */
    public function validate(): void
    {
        $this->validateWeekday();
        $this->validateTime($this->startsAt);
        $this->validateTime($this->endsAt);
    }

    /**
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     */
    public function toInterval(): ScheduleInterval
    {
        return new ScheduleInterval(
            weekday: Weekday::fromNumber($this->weekday),
            startsAt: TimeOfDay::fromString($this->startsAt),
            endsAt: TimeOfDay::fromString($this->endsAt),
        );
    }

    /**
     * @throws InvalidWeekday
     */
    private function validateWeekday(): void
    {
        Weekday::fromNumber($this->weekday);
    }

    /**
     * @throws InvalidTimeOfDay
     */
    private function validateTime(string $time): void
    {
        if (preg_match(self::HOURS_AND_MINUTES_SHAPE, $time) !== 1) {
            throw InvalidTimeOfDay::malformed($time);
        }
    }
}
