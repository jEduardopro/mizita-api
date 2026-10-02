<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class RegistrationPeriod
{
    public const MAXIMUM_YEARS = 5;

    private const CALENDAR_ZONE = 'UTC';

    private function __construct(
        public LocalDate $from,
        public LocalDate $to,
    ) {}

    /**
     * @throws InvalidCustomerRegistrationPeriod
     */
    public static function between(LocalDate $from, LocalDate $to): self
    {
        if ($from->isAfter($to)) {
            throw InvalidCustomerRegistrationPeriod::inverted($from->toString(), $to->toString());
        }

        if ($to->isAfter(self::latestEndFor($from))) {
            throw InvalidCustomerRegistrationPeriod::tooWide($from->toString(), $to->toString(), self::MAXIMUM_YEARS);
        }

        return new self($from, $to);
    }

    public function windowIn(DateTimeZone $zone): RegistrationWindow
    {
        return new RegistrationWindow(
            startsAt: $this->from->startsAtIn($zone),
            endsAt: $this->to->nextDay()->startsAtIn($zone),
        );
    }

    private static function latestEndFor(LocalDate $from): LocalDate
    {
        $start = new DateTimeImmutable($from->toString(), new DateTimeZone(self::CALENDAR_ZONE));

        return LocalDate::fromString(
            $start->add(new DateInterval('P'.self::MAXIMUM_YEARS.'Y'))->format(LocalDate::FORMAT),
        );
    }
}
