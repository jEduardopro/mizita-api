<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use DateTimeImmutable;

final readonly class NotifiedAppointmentData
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $serviceName,
        public ?string $referenceCode,
    ) {}

    public static function fromAppointment(NotifiedAppointment $appointment): self
    {
        return new self(
            id: $appointment->appointmentId,
            startsAt: $appointment->startsAt,
            endsAt: $appointment->endsAt,
            serviceName: $appointment->serviceName,
            referenceCode: $appointment->referenceCode,
        );
    }
}
