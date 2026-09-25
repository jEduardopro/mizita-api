<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Gateways;

use App\Domains\Integrations\Contracts\AppointmentFeed;
use App\Domains\Integrations\Exceptions\CalendarAppointmentNotFound;
use App\Domains\Integrations\ValueObjects\AppointmentLifecycle;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AppointmentsAppointmentFeed implements AppointmentFeed
{
    private const APPOINTMENTS_TABLE = 'appointments';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SELECTED_COLUMNS = [
        'appointments.uuid as appointment_id',
        'appointments.starts_at as starts_at',
        'appointments.ends_at as ends_at',
        'appointments.reference_code as reference_code',
        'appointments.cancelled_at as cancelled_at',
        'appointments.deleted_at as deleted_at',
        'businesses.uuid as business_id',
        'businesses.timezone as timezone',
        'businesses.deleted_at as business_deleted_at',
        'staff_members.uuid as staff_member_id',
        'services.name as service_name',
        'customers.name as customer_name',
    ];

    public function snapshotOf(string $appointmentId): AppointmentSnapshot
    {
        $row = DB::table(self::APPOINTMENTS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'appointments.business_id')
            ->join('staff_members', 'staff_members.id', '=', 'appointments.staff_member_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('customers', 'customers.id', '=', 'appointments.customer_id')
            ->where('appointments.uuid', $appointmentId)
            ->first(self::SELECTED_COLUMNS);

        if (! $row instanceof stdClass) {
            throw CalendarAppointmentNotFound::withId($appointmentId);
        }

        return new AppointmentSnapshot(
            appointmentId: (string) $row->appointment_id,
            businessId: (string) $row->business_id,
            staffMemberId: (string) $row->staff_member_id,
            startsAt: self::instantFrom($row->starts_at),
            endsAt: self::instantFrom($row->ends_at),
            serviceName: (string) $row->service_name,
            customerName: (string) $row->customer_name,
            referenceCode: (string) $row->reference_code,
            timezone: (string) $row->timezone,
            lifecycle: self::lifecycleOf($row),
        );
    }

    private static function lifecycleOf(stdClass $row): AppointmentLifecycle
    {
        if ($row->deleted_at !== null || $row->business_deleted_at !== null) {
            return AppointmentLifecycle::Deleted;
        }

        if ($row->cancelled_at !== null) {
            return AppointmentLifecycle::Cancelled;
        }

        return AppointmentLifecycle::Active;
    }

    private static function instantFrom(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable((string) $value))
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
