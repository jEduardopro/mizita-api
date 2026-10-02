<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\ValueObjects\BookedInterval;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AppointmentsBookedIntervals implements BookedIntervals
{
    private const APPOINTMENTS_TABLE = 'appointments';

    private const BUSINESSES_TABLE = 'businesses';

    private const SERVICES_TABLE = 'services';

    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const BLOCKED_UNTIL = "appointments.ends_at + (services.buffer_minutes * interval '1 minute')";

    private const OVERLAPS_INDEXED_RANGE = 'tstzrange(appointments.starts_at, appointments.ends_at)'.
        ' && tstzrange(cast(? as timestamptz), cast(? as timestamptz))';

    // Mirrors Services\ValueObjects\Buffer::MAXIMUM_MINUTES; raising that maximum without this hides buffered bookings.
    private const LONGEST_BUFFER_MINUTES = 1440;

    private const SELECTED_COLUMNS = [
        'appointments.starts_at as starts_at',
        'appointments.ends_at as ends_at',
        'services.buffer_minutes as buffer_minutes',
    ];

    private const STORAGE_TIMEZONE = 'UTC';

    /**
     * @return list<BookedInterval>
     */
    public function forStaffBetween(
        string $businessId,
        string $staffId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?string $excludingAppointmentId = null,
    ): array {
        $rows = DB::table(self::APPOINTMENTS_TABLE)
            ->join(self::BUSINESSES_TABLE, 'businesses.id', '=', 'appointments.business_id')
            ->join(self::SERVICES_TABLE, 'services.id', '=', 'appointments.service_id')
            ->join(self::STAFF_MEMBERS_TABLE, 'staff_members.id', '=', 'appointments.staff_member_id')
            ->where('businesses.uuid', $businessId)
            ->where('staff_members.uuid', $staffId)
            ->whereNull('appointments.deleted_at')
            ->whereNull('appointments.cancelled_at')
            ->when(
                $excludingAppointmentId !== null,
                static fn (QueryBuilder $query): QueryBuilder => $query->where(
                    'appointments.uuid',
                    '!=',
                    $excludingAppointmentId,
                ),
            )
            ->whereRaw(self::OVERLAPS_INDEXED_RANGE, [
                self::earliestBlockingEnd($from)->format(DATE_ATOM),
                $to->format(DATE_ATOM),
            ])
            ->where('appointments.starts_at', '<', $to->format(DATE_ATOM))
            ->whereRaw(self::BLOCKED_UNTIL.' > ?', [$from->format(DATE_ATOM)])
            ->orderBy('appointments.starts_at')
            ->get(self::SELECTED_COLUMNS);

        $intervals = [];

        foreach ($rows as $row) {
            $intervals[] = self::intervalFrom($row);
        }

        return $intervals;
    }

    private static function earliestBlockingEnd(DateTimeImmutable $from): DateTimeImmutable
    {
        return $from
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE))
            ->sub(new DateInterval('PT'.self::LONGEST_BUFFER_MINUTES.'M'));
    }

    private static function intervalFrom(stdClass $row): BookedInterval
    {
        $endsAt = self::instantFrom($row->ends_at);
        $buffer = (int) $row->buffer_minutes;

        return new BookedInterval(
            self::instantFrom($row->starts_at),
            $endsAt->add(new DateInterval('PT'.$buffer.'M')),
        );
    }

    private static function instantFrom(mixed $value): DateTimeImmutable
    {
        return (new DateTimeImmutable((string) $value))
            ->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
