<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\ValueObjects\Identifier;

final readonly class ShowCustomerLastAppointmentInput
{
    public function __construct(
        public string $customerId,
        public string $accountId,
    ) {}

    /**
     * @throws AppointmentCustomerNotFound
     * @throws CalendarNotAccessible
     */
    public function validate(): void
    {
        $this->validateCustomerId();
        $this->validateAccountId();
    }

    private function validateCustomerId(): void
    {
        if (! Identifier::isWellFormed($this->customerId)) {
            throw AppointmentCustomerNotFound::withId($this->customerId);
        }
    }

    private function validateAccountId(): void
    {
        if (! Identifier::isWellFormed($this->accountId)) {
            throw CalendarNotAccessible::forAccount($this->accountId);
        }
    }
}
