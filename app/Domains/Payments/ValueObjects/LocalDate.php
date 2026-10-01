<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LocalDate
{
    public const FORMAT = 'Y-m-d';

    private const AT_MIDNIGHT = '!'.self::FORMAT;

    private const DAY_START = ' 00:00:00';

    private const CALENDAR_ZONE = 'UTC';

    private const ONE_DAY = 'P1D';

    private function __construct(
        private DateTimeImmutable $day,
    ) {}

    /**
     * @throws InvalidPaymentReportPeriod
     */
    public static function fromString(string $value): self
    {
        $candidate = trim($value);
        $day = DateTimeImmutable::createFromFormat(self::AT_MIDNIGHT, $candidate, new DateTimeZone(self::CALENDAR_ZONE));

        if ($day === false || $day->format(self::FORMAT) !== $candidate) {
            throw InvalidPaymentReportPeriod::malformed($value);
        }

        return new self($day);
    }

    public function toString(): string
    {
        return $this->day->format(self::FORMAT);
    }

    public function isAfter(self $other): bool
    {
        return $this->day > $other->day;
    }

    public function nextDay(): self
    {
        return new self($this->day->add(new DateInterval(self::ONE_DAY)));
    }

    public function startsAtIn(DateTimeZone $zone): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->toString().self::DAY_START, $zone))
            ->setTimezone(new DateTimeZone(self::CALENDAR_ZONE));
    }
}
