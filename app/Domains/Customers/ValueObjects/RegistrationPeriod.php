<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use DateTimeZone;

final readonly class RegistrationPeriod
{
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

        return new self($from, $to);
    }

    public function windowIn(DateTimeZone $zone): RegistrationWindow
    {
        return new RegistrationWindow(
            startsAt: $this->from->startsAtIn($zone),
            endsAt: $this->to->nextDay()->startsAtIn($zone),
        );
    }
}
