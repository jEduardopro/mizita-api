<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Gateways;

use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\BookedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AppointmentsBookedAppointments implements BookedAppointments
{
    private const APPOINTMENTS_TABLE = 'appointments';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SELECTED_COLUMNS = [
        'appointments.uuid as appointment_id',
        'appointments.starts_at as starts_at',
        'appointments.ends_at as ends_at',
        'appointments.reference_code as reference_code',
        'businesses.uuid as business_id',
        'staff_members.uuid as staff_member_id',
        'services.name as service_name',
        'customers.uuid as customer_id',
        'customers.name as customer_name',
    ];

    public function describe(string $appointmentId): BookedAppointment
    {
        $row = DB::table(self::APPOINTMENTS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'appointments.business_id')
            ->join('staff_members', 'staff_members.id', '=', 'appointments.staff_member_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('customers', 'customers.id', '=', 'appointments.customer_id')
            ->where('appointments.uuid', $appointmentId)
            ->first(self::SELECTED_COLUMNS);

        if (! $row instanceof stdClass) {
            throw NotifiedAppointmentNotFound::withId($appointmentId);
        }

        return new BookedAppointment(
            businessId: (string) $row->business_id,
            staffMemberId: (string) $row->staff_member_id,
            appointment: new NotifiedAppointment(
                appointmentId: (string) $row->appointment_id,
                startsAt: self::instantFrom($row->starts_at),
                endsAt: self::instantFrom($row->ends_at),
                serviceName: (string) $row->service_name,
                referenceCode: (string) $row->reference_code,
            ),
            customer: new NotifiedCustomer(
                customerId: (string) $row->customer_id,
                name: (string) $row->customer_name,
            ),
        );
    }

    private static function instantFrom(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable((string) $value))
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
