<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\Clock;
use DateInterval;
use DateTimeImmutable;

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
