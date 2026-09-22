<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\ValueObjects\Identifier;

final readonly class ShowAppointmentPaymentInput
{
    public function __construct(
        public string $appointmentId,
    ) {}

    /**
     * @throws PaymentAppointmentNotFound
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw PaymentAppointmentNotFound::withId($this->appointmentId);
        }
    }
}
