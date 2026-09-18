<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use DateInterval;
use DateTimeImmutable;

final readonly class AppointmentSlot
{
    public const MAXIMUM_DURATION_MINUTES = 1440;

    public const MINIMUM_DURATION_MINUTES = 1;

    private const SECONDS_PER_MINUTE = 60;

    private function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}

    /**
     * @throws InvalidAppointmentSchedule
     */
    public static function between(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): self
    {
        $seconds = $endsAt->getTimestamp() - $startsAt->getTimestamp();

        if ($seconds < self::MINIMUM_DURATION_MINUTES * self::SECONDS_PER_MINUTE) {
            throw InvalidAppointmentSchedule::inverted();
        }

        if ($seconds > self::MAXIMUM_DURATION_MINUTES * self::SECONDS_PER_MINUTE) {
            throw InvalidAppointmentSchedule::tooLong(self::MAXIMUM_DURATION_MINUTES);
        }

        return new self($startsAt, $endsAt);
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public static function lasting(DateTimeImmutable $startsAt, int $durationMinutes): self
    {
        if ($durationMinutes < self::MINIMUM_DURATION_MINUTES) {
            throw InvalidAppointmentSchedule::tooShort();
        }

        if ($durationMinutes > self::MAXIMUM_DURATION_MINUTES) {
            throw InvalidAppointmentSchedule::tooLong(self::MAXIMUM_DURATION_MINUTES);
        }

        return new self($startsAt, $startsAt->add(new DateInterval("PT{$durationMinutes}M")));
    }

    public static function restore(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): self
    {
        return new self($startsAt, $endsAt);
    }

    public function durationMinutes(): int
    {
        return intdiv(
            $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp(),
            self::SECONDS_PER_MINUTE,
        );
    }
}
