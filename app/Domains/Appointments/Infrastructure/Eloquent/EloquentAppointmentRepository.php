<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Eloquent;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Infrastructure\Eloquent\Mappers\AppointmentMapper;
use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentAppointmentRepository implements AppointmentRepository
{
    private const OVERLAP_SQLSTATE = '23P01';

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
    ) {}

    /**
     * @return list<Appointment>
     */
    public function search(string $businessId, CalendarRange $range): array
    {
        $models = $this->ofBusiness($businessId)
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

    public function findForBusiness(string $businessId, string $id): Appointment
    {
        return $this->mapper->toEntity($this->modelOrFail($businessId, $id), $businessId);
    }

    public function save(Appointment $appointment): void
    {
        try {
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
        } catch (QueryException $violation) {
            $this->failFrom($violation);
        }
    }

    public function delete(string $businessId, string $id): void
    {
        $this->modelOrFail($businessId, $id)->delete();
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
