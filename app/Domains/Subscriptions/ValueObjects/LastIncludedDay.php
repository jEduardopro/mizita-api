<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionEndDate;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LastIncludedDay
{
    private const CALENDAR_DATE = 'Y-m-d';

    private const START_OF_DAY_FORMAT = '!Y-m-d';

    private const ONE_DAY = 'P1D';

    private const STORAGE_TIMEZONE = 'UTC';

    private function __construct(
        public string $date,
    ) {}

    /**
     * @throws InvalidSubscriptionEndDate
     */
    public static function fromString(string $value): self
    {
        $date = trim($value);

        if ($date === '') {
            throw InvalidSubscriptionEndDate::missing();
        }

        $parsed = DateTimeImmutable::createFromFormat(self::START_OF_DAY_FORMAT, $date, new DateTimeZone(self::STORAGE_TIMEZONE));

        if ($parsed === false || $parsed->format(self::CALENDAR_DATE) !== $date) {
            throw InvalidSubscriptionEndDate::malformed($value);
        }

        return new self($date);
    }

    public function exclusiveEndIn(DateTimeZone $zone): DateTimeImmutable
    {
        return $this->startOfDayIn($this->followingDate(), $zone)
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }

    private function followingDate(): string
    {
        return $this->startOfDayIn($this->date, new DateTimeZone(self::STORAGE_TIMEZONE))
            ->add(new DateInterval(self::ONE_DAY))
            ->format(self::CALENDAR_DATE);
    }

    private function startOfDayIn(string $date, DateTimeZone $zone): DateTimeImmutable
    {
        $startOfDay = DateTimeImmutable::createFromFormat(self::START_OF_DAY_FORMAT, $date, $zone);

        if ($startOfDay === false) {
            throw InvalidSubscriptionEndDate::malformed($date);
        }

        return $startOfDay;
    }
}
