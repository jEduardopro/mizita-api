<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use DateTimeImmutable;

final readonly class CalendarEventDraft
{
    public function __construct(
        public string $appointmentId,
        public string $title,
        public string $description,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $timezone,
    ) {}
}
