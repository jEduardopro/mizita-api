<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;

final readonly class BookedAppointment
{
    public function __construct(
        public string $businessId,
        public string $staffMemberId,
        public NotifiedAppointment $appointment,
        public NotifiedCustomer $customer,
    ) {}

    public function payload(): AppointmentBookedPayload
    {
        return new AppointmentBookedPayload($this->appointment, $this->customer);
    }
}
