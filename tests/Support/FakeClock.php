<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\Clock;
use DateInterval;
use DateTimeImmutable;

/**
 * A Clock frozen at a known instant.
 *
 * Time is never real in a test: a frozen clock is what makes a timestamp on an
 * entity assertable, and advance() is what makes a date rule reachable without
 * waiting for the calendar.
 */
final class FakeClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new DateInterval($interval));
    }
}
