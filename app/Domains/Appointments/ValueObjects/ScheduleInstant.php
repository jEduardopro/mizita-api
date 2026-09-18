<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final readonly class ScheduleInstant
{
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/D';

    private const STORAGE_TIMEZONE = 'UTC';

    private function __construct(
        public DateTimeImmutable $value,
    ) {}

    /**
     * @throws InvalidAppointmentSchedule
     */
    public static function fromString(string $value): self
    {
        $instant = trim($value);

        if (preg_match(self::PATTERN, $instant) !== 1) {
            throw InvalidAppointmentSchedule::malformed();
        }

        if (! self::hasRealCalendarDate($instant)) {
            throw InvalidAppointmentSchedule::malformed();
        }

        try {
            $parsed = new DateTimeImmutable($instant);
        } catch (Exception $unreadable) {
            throw InvalidAppointmentSchedule::malformed();
        }

        return new self($parsed->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE)));
    }

    private static function hasRealCalendarDate(string $instant): bool
    {
        [$year, $month, $day] = array_map('intval', explode('-', substr($instant, 0, 10)));

        return checkdate($month, $day, $year);
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::fromString($value);
    }
}
