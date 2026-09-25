<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent;

use App\Domains\Integrations\Contracts\CalendarConnectionRepository;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAlreadyConnected;
use App\Domains\Integrations\Exceptions\CalendarConnectionNotFound;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\Infrastructure\Eloquent\Mappers\CalendarConnectionMapper;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentCalendarConnectionRepository implements CalendarConnectionRepository
{
    private const BUSINESSES_TABLE = 'businesses';

    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const STAFF_SELECTION = 'staffMember:id,uuid';

    public function __construct(
        private readonly CalendarConnectionMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForStaffMember(string $businessId, string $staffMemberId, CalendarProvider $provider): ?CalendarConnection
    {
        $model = $this->ofBusiness($businessId)
            ->with(self::STAFF_SELECTION)
            ->where('provider', $provider->value)
            ->whereIn('staff_member_id', static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::STAFF_MEMBERS_TABLE)
                ->where('uuid', $staffMemberId))
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    public function findInBusiness(string $businessId, string $id): ?CalendarConnection
    {
        $model = $this->ofBusiness($businessId)->with(self::STAFF_SELECTION)->where('uuid', $id)->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    public function findDisconnected(string $businessId, string $id): ?CalendarConnection
    {
        $model = $this->ofBusiness($businessId)
            ->onlyTrashed()
            ->with(self::STAFF_SELECTION)
            ->where('uuid', $id)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model, $businessId);
    }

    public function existsLiveForAccount(CalendarProvider $provider, string $accountEmail): bool
    {
        return CalendarConnectionModel::query()
            ->where('provider', $provider->value)
            ->whereRaw('lower(account_email) = lower(?)', [trim($accountEmail)])
            ->exists();
    }

    /**
     * @throws CalendarAlreadyConnected
     */
    public function saveWithTokens(CalendarConnection $connection, CalendarTokens $tokens): void
    {
        $businessKey = $this->businessKeys->teamKeyFor($connection->businessId);

        try {
            CalendarConnectionModel::query()->updateOrCreate(
                ['uuid' => $connection->id],
                [
                    ...$this->mapper->toAttributes(
                        $connection,
                        $businessKey,
                        $this->staffMemberKeyFor($businessKey, $connection->staffMemberId),
                    ),
                    ...$this->mapper->tokenAttributes($tokens),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw CalendarAlreadyConnected::forStaffMember($connection->staffMemberId);
        }
    }

    public function update(CalendarConnection $connection): void
    {
        $model = $this->ofBusiness($connection->businessId)->where('uuid', $connection->id)->first()
            ?? throw CalendarConnectionNotFound::withId($connection->id);

        $model->fill($this->mapper->toAttributes(
            $connection,
            (int) $model->business_id,
            (int) $model->staff_member_id,
        ))->save();
    }

    public function delete(string $businessId, string $id): void
    {
        $model = $this->ofBusiness($businessId)->where('uuid', $id)->first()
            ?? throw CalendarConnectionNotFound::withId($id);

        $model->delete();
    }

    /**
     * @return Builder<CalendarConnectionModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return CalendarConnectionModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }

    /**
     * @throws CalendarOwnerNotFound
     */
    private function staffMemberKeyFor(int $businessKey, string $staffMemberId): int
    {
        $key = DB::table(self::STAFF_MEMBERS_TABLE)
            ->where('business_id', $businessKey)
            ->where('uuid', $staffMemberId)
            ->whereNull('deleted_at')
            ->value('id');

        if ($key === null) {
            throw CalendarOwnerNotFound::withId($staffMemberId);
        }

        return (int) $key;
    }
}
