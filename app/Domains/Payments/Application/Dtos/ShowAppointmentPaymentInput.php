<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\ValueObjects\Identifier;

final readonly class ShowAppointmentPaymentInput
{
    public function __construct(
        public string $appointmentId,
        public string $actorAccountId,
    ) {}

    /**
     * @throws PaymentAppointmentNotFound
     * @throws InvalidPaymentActor
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
        $this->validateActorAccountId();
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw PaymentAppointmentNotFound::withId($this->appointmentId);
        }
    }

    private function validateActorAccountId(): void
    {
        if (! Identifier::isWellFormed($this->actorAccountId)) {
            throw InvalidPaymentActor::malformed($this->actorAccountId);
        }
    }
}
