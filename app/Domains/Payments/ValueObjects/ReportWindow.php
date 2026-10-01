<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use DateTimeImmutable;

final readonly class ReportWindow
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}
}
