<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use DateTimeZone;

final readonly class ReportPeriod
{
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

        return new self($from, $to);
    }

    public function windowIn(DateTimeZone $zone): ReportWindow
    {
        return new ReportWindow(
            startsAt: $this->from->startsAtIn($zone),
            endsAt: $this->to->nextDay()->startsAtIn($zone),
        );
    }
}
