<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Eloquent;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Infrastructure\Eloquent\Mappers\AppointmentMapper;
use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarScope;
use App\Domains\Appointments\ValueObjects\CustomerAppointmentQuery;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\ValueObjects\Paginated;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentAppointmentRepository implements AppointmentRepository
{
    private const OVERLAP_SQLSTATE = '23P01';

    private const REFERENCE_CODE_UNIQUE_INDEX = 'appointments_reference_code_unique';

    private const MAXIMUM_REFERENCE_CODE_ATTEMPTS = 5;

    private const BUSINESSES_TABLE = 'businesses';

    private const CUSTOMERS_TABLE = 'customers';

    private const SERVICES_TABLE = 'services';

    private const STAFF_MEMBERS_TABLE = 'staff_members';

    /**
     * @var list<string>
     */
    private const PARTICIPANT_RELATIONS = ['customer', 'service', 'staffMember'];

    private const TIEBREAKER_COLUMN = 'id';

    public function __construct(
        private readonly AppointmentMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
        private readonly ReferenceCodeGenerator $referenceCodes,
    ) {}

    /**
     * @return list<Appointment>
     */
    public function search(string $businessId, CalendarRange $range, CalendarScope $scope): array
    {
        $models = $this->withinScope($this->ofBusiness($businessId), $scope)
            ->with(self::PARTICIPANT_RELATIONS)
            ->where('starts_at', '<', $range->to->format(DATE_ATOM))
            ->where('ends_at', '>', $range->from->format(DATE_ATOM))
            ->orderBy('starts_at')
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->get();

        return array_map(
            fn (AppointmentModel $model): Appointment => $this->mapper->toEntity($model, $businessId),
            $models->all(),
        );
    }

    /**
     * @return Paginated<Appointment>
     */
    public function bookedForCustomer(
        string $businessId,
        CustomerAppointmentQuery $query,
        CalendarScope $scope,
    ): Paginated {
        $matching = $this->withinScope($this->ofBusiness($businessId), $scope)
            ->whereIn(
                'customer_id',
                static fn (QueryBuilder $customers) => $customers
                    ->select('id')
                    ->from(self::CUSTOMERS_TABLE)
                    ->where('uuid', $query->customerId),
            )
            ->whereNull('cancelled_at');

        $total = $matching->count();

        $models = $matching->with(self::PARTICIPANT_RELATIONS)
            ->orderByDesc('starts_at')
            ->orderByDesc(self::TIEBREAKER_COLUMN)
            ->offset($query->pagination->offset())
            ->limit($query->pagination->perPage)
            ->get();

        return Paginated::of(
            array_map(
                fn (AppointmentModel $model): Appointment => $this->mapper->toEntity($model, $businessId),
                $models->all(),
            ),
            $total,
            $query->pagination,
        );
    }

    public function findForBusiness(string $businessId, string $id): Appointment
    {
        return $this->mapper->toEntity($this->modelOrFail($businessId, $id), $businessId);
    }

    public function findWithinScope(string $businessId, string $id, CalendarScope $scope): Appointment
    {
        $model = $this->withinScope($this->ofBusiness($businessId), $scope)
            ->with(self::PARTICIPANT_RELATIONS)
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw AppointmentNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    public function hasUpcomingForStaffMember(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool
    {
        return $this->ofStaffMember($this->ofBusiness($businessId), $staffMemberId)
            ->whereNull('cancelled_at')
            ->where('ends_at', '>', $now->format(DATE_ATOM))
            ->exists();
    }

    public function findByReferenceCode(string $businessId, string $referenceCode): ?Appointment
    {
        $model = $this->ofBusiness($businessId)
            ->with(self::PARTICIPANT_RELATIONS)
            ->where('reference_code', $referenceCode)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    public function save(Appointment $appointment): void
    {
        if ($appointment->referenceCode() === null) {
            $appointment->assignReferenceCode($this->referenceCodes->next());
        }

        $remainingAttempts = self::MAXIMUM_REFERENCE_CODE_ATTEMPTS;

        while (true) {
            try {
                $this->persist($appointment);

                return;
            } catch (QueryException $violation) {
                $remainingAttempts--;

                if ($remainingAttempts === 0 || ! self::isReferenceCodeCollision($violation)) {
                    $this->failFrom($violation);
                }

                $appointment->assignReferenceCode($this->referenceCodes->next());
            }
        }
    }

    public function delete(string $businessId, string $id): void
    {
        $this->modelOrFail($businessId, $id)->delete();
    }

    private function persist(Appointment $appointment): void
    {
        AppointmentModel::query()->updateOrCreate(
            ['uuid' => $appointment->id],
            $this->mapper->toAttributes(
                $appointment,
                $this->businessKeys->teamKeyFor($appointment->businessId),
                $this->customerKeyFor($appointment->customerId()),
                $this->serviceKeyFor($appointment->serviceId()),
                $this->staffMemberKeyFor($appointment->staffMemberId()),
            ),
        );
    }

    private static function isReferenceCodeCollision(QueryException $violation): bool
    {
        return str_contains($violation->getMessage(), self::REFERENCE_CODE_UNIQUE_INDEX);
    }

    private function modelOrFail(string $businessId, string $id): AppointmentModel
    {
        $model = $this->ofBusiness($businessId)
            ->with(self::PARTICIPANT_RELATIONS)
            ->where('uuid', $id)
            ->first();

        if ($model === null) {
            throw AppointmentNotFound::withId($id);
        }

        return $model;
    }

    /**
     * @return Builder<AppointmentModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return AppointmentModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    /**
     * @param  Builder<AppointmentModel>  $query
     * @return Builder<AppointmentModel>
     */
    private function withinScope(Builder $query, CalendarScope $scope): Builder
    {
        $staffMemberId = $scope->restrictedStaffMemberId();

        if ($staffMemberId === null) {
            return $query;
        }

        return $this->ofStaffMember($query, $staffMemberId);
    }

    /**
     * @param  Builder<AppointmentModel>  $query
     * @return Builder<AppointmentModel>
     */
    private function ofStaffMember(Builder $query, string $staffMemberId): Builder
    {
        return $query->whereIn(
            'staff_member_id',
            static fn (QueryBuilder $members) => $members
                ->select('id')
                ->from(self::STAFF_MEMBERS_TABLE)
                ->where('uuid', $staffMemberId),
        );
    }

    /**
     * @throws AppointmentCustomerNotFound
     */
    private function customerKeyFor(string $customerId): int
    {
        return self::keyOf(self::CUSTOMERS_TABLE, $customerId)
            ?? throw AppointmentCustomerNotFound::withId($customerId);
    }

    /**
     * @throws AppointmentServiceNotFound
     */
    private function serviceKeyFor(string $serviceId): int
    {
        return self::keyOf(self::SERVICES_TABLE, $serviceId)
            ?? throw AppointmentServiceNotFound::withId($serviceId);
    }

    /**
     * @throws AppointmentStaffNotFound
     */
    private function staffMemberKeyFor(string $staffMemberId): int
    {
        return self::keyOf(self::STAFF_MEMBERS_TABLE, $staffMemberId)
            ?? throw AppointmentStaffNotFound::withId($staffMemberId);
    }

    private static function keyOf(string $table, string $uuid): ?int
    {
        $key = DB::table($table)->where('uuid', $uuid)->whereNull('deleted_at')->value('id');

        return $key === null ? null : (int) $key;
    }

    /**
     * @throws AppointmentOverlaps
     */
    private function failFrom(QueryException $violation): never
    {
        if ((string) $violation->getCode() === self::OVERLAP_SQLSTATE) {
            throw AppointmentOverlaps::withAnotherBooking($violation);
        }

        throw $violation;
    }
}
