<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Gateways;

use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\BookedAppointment;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AppointmentsBookedAppointments implements BookedAppointments
{
    private const APPOINTMENTS_TABLE = 'appointments';

    private const SELECTED_COLUMNS = [
        'appointments.uuid as appointment_id',
        'businesses.uuid as business_id',
        'staff_members.uuid as staff_member_id',
    ];

    public function recipientOf(string $appointmentId): BookedAppointment
    {
        $row = DB::table(self::APPOINTMENTS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'appointments.business_id')
            ->join('staff_members', 'staff_members.id', '=', 'appointments.staff_member_id')
            ->where('appointments.uuid', $appointmentId)
            ->first(self::SELECTED_COLUMNS);

        if (! $row instanceof stdClass) {
            throw NotifiedAppointmentNotFound::withId($appointmentId);
        }

        return new BookedAppointment(
            appointmentId: (string) $row->appointment_id,
            businessId: (string) $row->business_id,
            staffMemberId: (string) $row->staff_member_id,
        );
    }
}
