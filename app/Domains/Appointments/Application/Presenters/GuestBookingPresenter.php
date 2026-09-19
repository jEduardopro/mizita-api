<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Presenters;

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use DateTimeImmutable;

final class GuestBookingPresenter
{
    public function __construct(
        private readonly ServiceCatalog $services,
        private readonly CustomerDirectory $customers,
        private readonly StaffDirectory $staff,
    ) {}

    /**
     * @throws GuestBookingNotFound
     * @throws AppointmentCustomerNotFound
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     */
    public function describe(
        string $businessId,
        Appointment $appointment,
        CancellationRule $rule,
        DateTimeImmutable $now,
    ): GuestBookingData {
        return GuestBookingData::fromEntity(
            $appointment,
            self::referenceCodeOf($appointment),
            $this->customers->describe($businessId, $appointment->customerId()),
            $this->services->describe($businessId, $appointment->serviceId()),
            $this->staff->describe($businessId, $appointment->staffMemberId()),
            $rule,
            $now,
        );
    }

    /**
     * @throws GuestBookingNotFound
     */
    private static function referenceCodeOf(Appointment $appointment): string
    {
        return $appointment->referenceCode()?->value ?? throw GuestBookingNotFound::forCredentials();
    }
}
