<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Presenters;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;

final class AppointmentPresenter
{
    public function __construct(
        private readonly ServiceCatalog $services,
        private readonly CustomerDirectory $customers,
        private readonly StaffDirectory $staff,
    ) {}

    public function describe(string $businessId, Appointment $appointment): AppointmentData
    {
        return AppointmentData::fromEntity(
            $appointment,
            $this->customers->describe($businessId, $appointment->customerId()),
            $this->services->describe($businessId, $appointment->serviceId()),
            $this->staff->describe($businessId, $appointment->staffMemberId()),
        );
    }

    /**
     * @param  list<Appointment>  $appointments
     * @return list<AppointmentData>
     */
    public function describeMany(string $businessId, array $appointments): array
    {
        if ($appointments === []) {
            return [];
        }

        $customers = $this->customers->describeMany($businessId, self::customerIdsOf($appointments));
        $services = $this->services->describeMany($businessId, self::serviceIdsOf($appointments));
        $members = $this->staff->describeMany($businessId, self::staffMemberIdsOf($appointments));

        return array_map(
            fn (Appointment $appointment): AppointmentData => AppointmentData::fromEntity(
                $appointment,
                self::customerOf($appointment, $customers),
                self::serviceOf($appointment, $services),
                self::staffMemberOf($appointment, $members),
            ),
            $appointments,
        );
    }

    /**
     * @param  list<Appointment>  $appointments
     * @return list<string>
     */
    private static function customerIdsOf(array $appointments): array
    {
        return array_values(array_unique(array_map(
            static fn (Appointment $appointment): string => $appointment->customerId(),
            $appointments,
        )));
    }

    /**
     * @param  list<Appointment>  $appointments
     * @return list<string>
     */
    private static function serviceIdsOf(array $appointments): array
    {
        return array_values(array_unique(array_map(
            static fn (Appointment $appointment): string => $appointment->serviceId(),
            $appointments,
        )));
    }

    /**
     * @param  list<Appointment>  $appointments
     * @return list<string>
     */
    private static function staffMemberIdsOf(array $appointments): array
    {
        return array_values(array_unique(array_map(
            static fn (Appointment $appointment): string => $appointment->staffMemberId(),
            $appointments,
        )));
    }

    /**
     * @param  array<string, CustomerSnapshot>  $customers
     *
     * @throws AppointmentCustomerNotFound
     */
    private static function customerOf(Appointment $appointment, array $customers): CustomerSnapshot
    {
        return $customers[$appointment->customerId()]
            ?? throw AppointmentCustomerNotFound::withId($appointment->customerId());
    }

    /**
     * @param  array<string, ServiceSnapshot>  $services
     *
     * @throws AppointmentServiceNotFound
     */
    private static function serviceOf(Appointment $appointment, array $services): ServiceSnapshot
    {
        return $services[$appointment->serviceId()]
            ?? throw AppointmentServiceNotFound::withId($appointment->serviceId());
    }

    /**
     * @param  array<string, StaffMemberSnapshot>  $members
     *
     * @throws AppointmentStaffNotFound
     */
    private static function staffMemberOf(Appointment $appointment, array $members): StaffMemberSnapshot
    {
        return $members[$appointment->staffMemberId()]
            ?? throw AppointmentStaffNotFound::withId($appointment->staffMemberId());
    }
}
