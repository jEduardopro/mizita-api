<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReportPeriod
{
    public const MAXIMUM_YEARS = 5;

    private const CALENDAR_ZONE = 'UTC';

    private function __construct(
        public LocalDate $from,
        public LocalDate $to,
    ) {}

    /**
     * @throws InvalidPaymentReportPeriod
     */
    public static function between(LocalDate $from, LocalDate $to): self
    {
        if ($from->isAfter($to)) {
            throw InvalidPaymentReportPeriod::inverted($from->toString(), $to->toString());
        }

        if ($to->isAfter(self::latestEndFor($from))) {
            throw InvalidPaymentReportPeriod::tooWide($from->toString(), $to->toString(), self::MAXIMUM_YEARS);
        }

        return new self($from, $to);
    }

    public function windowIn(DateTimeZone $zone): ReportWindow
    {
        return new ReportWindow(
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
