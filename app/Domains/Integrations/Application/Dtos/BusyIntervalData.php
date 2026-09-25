<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

use App\Domains\Integrations\ValueObjects\BusyInterval;
use DateTimeImmutable;

final readonly class BusyIntervalData
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}

    public static function fromInterval(BusyInterval $interval): self
    {
        return new self($interval->startsAt, $interval->endsAt);
    }
}
