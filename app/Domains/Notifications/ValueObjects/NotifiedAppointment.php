<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

use DateTimeImmutable;

final readonly class NotifiedAppointment
{
    public function __construct(
        public string $appointmentId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $serviceName,
        public string $referenceCode,
    ) {}
}
