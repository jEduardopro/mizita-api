<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Infrastructure\Gateways;

use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\ValueObjects\AppointmentTally;
use App\Domains\Statistics\ValueObjects\CollectedTransactionType;
use App\Domains\Statistics\ValueObjects\CustomerTally;
use App\Domains\Statistics\ValueObjects\PaymentMethodCollection;
use App\Domains\Statistics\ValueObjects\ReportingWindow;
use App\Domains\Statistics\ValueObjects\StaffPerformance;
use App\Shared\Contracts\BusinessTeamKey;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentStatisticsReader implements StatisticsReader
{
    private const TRANSACTIONS_TABLE = 'payment_transactions';

    private const PAYMENTS_TABLE = 'payments';

    private const PAYMENT_METHODS_TABLE = 'payment_methods';

    private const APPOINTMENTS_TABLE = 'appointments';

    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const USERS_TABLE = 'users';

    private const COLLECTED_BY_STAFF = 'collected_by_staff';

    private const ATTENDED_BY_STAFF = 'attended_by_staff';

    private const ATTENDED_CUSTOMERS = 'attended_customers';

    private const STORAGE_TIMEZONE = 'UTC';

    private const LOCAL_DATE_FORMAT = 'YYYY-MM-DD';

    public function __construct(
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function netCollectedCents(string $businessId, ReportingWindow $window): int
    {
        $row = $this->transactionsIn($this->keyOf($businessId), $window)
            ->selectRaw(self::collectedColumn())
            ->first();

        return self::integer($row?->collected_cents);
    }

    /**
     * @return array<string, int>
     */
    public function netCollectedCentsPerLocalDay(string $businessId, ReportingWindow $window, DateTimeZone $zone): array
    {
        $rows = $this->transactionsIn($this->keyOf($businessId), $window)
            ->selectRaw(
                "to_char(payment_transactions.processed_at at time zone ?, '".self::LOCAL_DATE_FORMAT."') as local_date",
                [$zone->getName()],
            )
            ->selectRaw(self::collectedColumn())
            ->groupBy('local_date')
            ->get();

        $collectedPerDay = [];

        foreach ($rows as $row) {
            $collectedPerDay[(string) $row->local_date] = self::integer($row->collected_cents);
        }

        return $collectedPerDay;
    }

    /**
     * @return list<PaymentMethodCollection>
     */
    public function netCollectedByPaymentMethod(string $businessId, ReportingWindow $window): array
    {
        $rows = $this->transactionsIn($this->keyOf($businessId), $window)
            ->join(self::PAYMENT_METHODS_TABLE, 'payment_methods.id', '=', 'payment_transactions.payment_method_id')
            ->select('payment_methods.code as code')
            ->selectRaw(self::collectedColumn())
            ->groupBy('payment_methods.code')
            ->orderByDesc('collected_cents')
            ->orderBy('payment_methods.code')
            ->get();

        $methods = [];

        foreach ($rows as $row) {
            $methods[] = new PaymentMethodCollection((string) $row->code, self::integer($row->collected_cents));
        }

        return $methods;
    }

    /**
     * @return list<StaffPerformance>
     */
    public function staffPerformance(string $businessId, ReportingWindow $window, DateTimeImmutable $now): array
    {
        $businessKey = $this->keyOf($businessId);

        $rows = DB::table(self::STAFF_MEMBERS_TABLE)
            ->join(self::USERS_TABLE, 'users.id', '=', 'staff_members.account_id')
            ->leftJoinSub(
                $this->collectedByStaff($businessKey, $window),
                self::COLLECTED_BY_STAFF,
                'collected_by_staff.staff_member_id',
                '=',
                'staff_members.id',
            )
            ->leftJoinSub(
                $this->attendedByStaff($businessKey, $window, $now),
                self::ATTENDED_BY_STAFF,
                'attended_by_staff.staff_member_id',
                '=',
                'staff_members.id',
            )
            ->where('staff_members.business_id', $businessKey)
            ->where(static fn (Builder $query): Builder => $query
                ->where('collected_by_staff.collected_cents', '!=', 0)
                ->orWhere('attended_by_staff.attended_appointments', '>', 0))
            ->select([
                'staff_members.uuid as staff_member_id',
                'users.name as name',
                'users.email as email',
            ])
            ->selectRaw('coalesce(collected_by_staff.collected_cents, 0) as collected_cents')
            ->selectRaw('coalesce(attended_by_staff.attended_appointments, 0) as attended_appointments')
            ->orderByDesc('collected_cents')
            ->orderByDesc('attended_appointments')
            ->orderBy('users.name')
            ->get();

        $staff = [];

        foreach ($rows as $row) {
            $staff[] = self::performanceFrom($row);
        }

        return $staff;
    }

    public function appointmentTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): AppointmentTally
    {
        $businessKey = $this->keyOf($businessId);
        $instant = self::instant($now);

        $counts = $this->appointmentsStartingIn($businessKey, $window)
            ->selectRaw('count(*) as total')
            ->selectRaw(
                'count(*) filter (where appointments.cancelled_at is null and appointments.ends_at <= ?) as attended',
                [$instant],
            )
            ->selectRaw('count(*) filter (where appointments.cancelled_at is not null) as cancelled')
            ->selectRaw(
                'count(*) filter (where appointments.cancelled_at is null and appointments.starts_at > ?) as upcoming',
                [$instant],
            )
            ->first();

        return new AppointmentTally(
            total: self::integer($counts?->total),
            attended: self::integer($counts?->attended),
            cancelled: self::integer($counts?->cancelled),
            upcoming: self::integer($counts?->upcoming),
            bookedBySource: $this->bookedBySource($businessKey, $window),
        );
    }

    public function customerTally(string $businessId, ReportingWindow $window, DateTimeImmutable $now): CustomerTally
    {
        $startsAt = self::instant($window->startsAt);

        $attendedCustomers = DB::table(self::APPOINTMENTS_TABLE)
            ->where('appointments.business_id', $this->keyOf($businessId))
            ->whereNull('appointments.deleted_at')
            ->whereNull('appointments.cancelled_at')
            ->where('appointments.ends_at', '<=', self::instant($now))
            ->where('appointments.starts_at', '<', self::instant($window->endsAt))
            ->groupBy('appointments.customer_id')
            ->havingRaw('max(appointments.starts_at) >= ?', [$startsAt])
            ->select('appointments.customer_id')
            ->selectRaw('min(appointments.starts_at) as first_attended_at');

        $counts = DB::query()
            ->fromSub($attendedCustomers, self::ATTENDED_CUSTOMERS)
            ->selectRaw('count(*) as attended')
            ->selectRaw('count(*) filter (where attended_customers.first_attended_at >= ?) as newcomers', [$startsAt])
            ->first();

        return new CustomerTally(
            attended: self::integer($counts?->attended),
            new: self::integer($counts?->newcomers),
        );
    }

    private function keyOf(string $businessId): int
    {
        return $this->businessKeys->teamKeyFor($businessId);
    }

    private function transactionsIn(int $businessKey, ReportingWindow $window): Builder
    {
        return DB::table(self::TRANSACTIONS_TABLE)
            ->join(self::PAYMENTS_TABLE, 'payments.id', '=', 'payment_transactions.payment_id')
            ->where('payments.business_id', $businessKey)
            ->whereNull('payments.deleted_at')
            ->whereNull('payment_transactions.deleted_at')
            ->whereIn('payment_transactions.type', CollectedTransactionType::values())
            ->where('payment_transactions.processed_at', '>=', self::instant($window->startsAt))
            ->where('payment_transactions.processed_at', '<', self::instant($window->endsAt));
    }

    private function appointmentsStartingIn(int $businessKey, ReportingWindow $window): Builder
    {
        return DB::table(self::APPOINTMENTS_TABLE)
            ->where('appointments.business_id', $businessKey)
            ->whereNull('appointments.deleted_at')
            ->where('appointments.starts_at', '>=', self::instant($window->startsAt))
            ->where('appointments.starts_at', '<', self::instant($window->endsAt));
    }

    private function collectedByStaff(int $businessKey, ReportingWindow $window): Builder
    {
        return $this->transactionsIn($businessKey, $window)
            ->join(self::APPOINTMENTS_TABLE, 'appointments.id', '=', 'payments.appointment_id')
            ->select('appointments.staff_member_id')
            ->selectRaw(self::collectedColumn())
            ->groupBy('appointments.staff_member_id');
    }

    private function attendedByStaff(int $businessKey, ReportingWindow $window, DateTimeImmutable $now): Builder
    {
        return $this->appointmentsStartingIn($businessKey, $window)
            ->whereNull('appointments.cancelled_at')
            ->where('appointments.ends_at', '<=', self::instant($now))
            ->select('appointments.staff_member_id')
            ->selectRaw('count(*) as attended_appointments')
            ->groupBy('appointments.staff_member_id');
    }

    /**
     * @return array<string, int>
     */
    private function bookedBySource(int $businessKey, ReportingWindow $window): array
    {
        $rows = $this->appointmentsStartingIn($businessKey, $window)
            ->select('appointments.source as source')
            ->selectRaw('count(*) as booked')
            ->groupBy('appointments.source')
            ->get();

        $bookedBySource = [];

        foreach ($rows as $row) {
            $bookedBySource[(string) $row->source] = self::integer($row->booked);
        }

        return $bookedBySource;
    }

    private static function collectedColumn(): string
    {
        return 'coalesce(sum('.self::signedAmount().'), 0) as collected_cents';
    }

    private static function signedAmount(): string
    {
        $branches = array_map(
            static fn (CollectedTransactionType $type): string => sprintf(
                "when '%s' then %d * payment_transactions.total_cents",
                $type->value,
                $type->sign(),
            ),
            CollectedTransactionType::cases(),
        );

        return 'case payment_transactions.type '.implode(' ', $branches).' else 0 end';
    }

    private static function performanceFrom(stdClass $row): StaffPerformance
    {
        return new StaffPerformance(
            staffMemberId: (string) $row->staff_member_id,
            name: (string) $row->name,
            email: (string) $row->email,
            collectedCents: self::integer($row->collected_cents),
            attendedAppointments: self::integer($row->attended_appointments),
        );
    }

    private static function instant(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE))->format(DATE_ATOM);
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
