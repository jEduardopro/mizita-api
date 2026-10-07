<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\Identifier;

final readonly class NotifyAppointmentBookedInput
{
    public function __construct(
        public string $appointmentId,
    ) {}

    /**
     * @throws NotifiedAppointmentNotFound
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw NotifiedAppointmentNotFound::withId($this->appointmentId);
        }
    }
}
