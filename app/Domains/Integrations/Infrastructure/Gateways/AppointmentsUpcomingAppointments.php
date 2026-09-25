<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Gateways;

use App\Domains\Integrations\Contracts\UpcomingAppointments;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class AppointmentsUpcomingAppointments implements UpcomingAppointments
{
    private const APPOINTMENTS_TABLE = 'appointments';

    private const MAXIMUM_BACKFILLED_APPOINTMENTS = 1000;

    /**
     * @return list<string>
     */
    public function activeIdsFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): array
    {
        return DB::table(self::APPOINTMENTS_TABLE)
            ->join('businesses', 'businesses.id', '=', 'appointments.business_id')
            ->join('staff_members', 'staff_members.id', '=', 'appointments.staff_member_id')
            ->where('businesses.uuid', $businessId)
            ->where('staff_members.uuid', $staffMemberId)
            ->whereNull('appointments.deleted_at')
            ->whereNull('appointments.cancelled_at')
            ->where('appointments.ends_at', '>', $now->format(DATE_ATOM))
            ->orderBy('appointments.starts_at')
            ->limit(self::MAXIMUM_BACKFILLED_APPOINTMENTS)
            ->pluck('appointments.uuid')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }
}
