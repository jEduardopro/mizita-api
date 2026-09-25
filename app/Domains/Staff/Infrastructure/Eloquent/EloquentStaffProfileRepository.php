<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent;

use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;
use App\Domains\Staff\Infrastructure\Eloquent\Mappers\StaffProfileMapper;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStaffProfileRepository implements StaffProfileRepository
{
    private const STAFF_MEMBERS_TABLE = 'staff_members';

    public function __construct(
        private readonly StaffProfileMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForStaffMember(string $businessId, string $staffMemberId): StaffProfile
    {
        $businessKey = $this->businessKeys->teamKeyFor($businessId);

        $model = StaffProfileModel::query()
            ->where('business_id', $businessKey)
            ->whereIn(
                'staff_member_id',
                static fn (QueryBuilder $query) => $query
                    ->select('id')
                    ->from(self::STAFF_MEMBERS_TABLE)
                    ->where('business_id', $businessKey)
                    ->where('uuid', $staffMemberId)
                    ->whereNull('deleted_at'),
            )
            ->first();

        if ($model === null) {
            throw StaffProfileNotFound::forStaffMember($staffMemberId);
        }

        return $this->mapper->toEntity($model, $businessId, $staffMemberId);
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, StaffProfile>
     */
    public function findForStaffMembers(string $businessId, array $staffMemberIds): array
    {
        if ($staffMemberIds === []) {
            return [];
        }

        $businessKey = $this->businessKeys->teamKeyFor($businessId);

        $models = StaffProfileModel::query()
            ->with('staffMember')
            ->where('business_id', $businessKey)
            ->whereIn(
                'staff_member_id',
                static fn (QueryBuilder $query) => $query
                    ->select('id')
                    ->from(self::STAFF_MEMBERS_TABLE)
                    ->where('business_id', $businessKey)
                    ->whereIn('uuid', $staffMemberIds)
                    ->whereNull('deleted_at'),
            )
            ->get();

        $profiles = [];

        foreach ($models as $model) {
            $staffMemberId = $model->staffMember?->uuid;

            if ($staffMemberId === null) {
                continue;
            }

            $profiles[$staffMemberId] = $this->mapper->toEntity($model, $businessId, $staffMemberId);
        }

        return $profiles;
    }

    public function save(StaffProfile $profile): void
    {
        $businessKey = $this->businessKeys->teamKeyFor($profile->businessId);

        StaffProfileModel::query()->updateOrCreate(
            ['uuid' => $profile->id],
            $this->mapper->toAttributes(
                $profile,
                $businessKey,
                $this->staffMemberKey($businessKey, $profile->staffMemberId),
            ),
        );
    }

    private function staffMemberKey(int $businessKey, string $staffMemberId): int
    {
        $key = StaffMemberModel::query()
            ->where('business_id', $businessKey)
            ->where('uuid', $staffMemberId)
            ->value('id');

        if ($key === null) {
            throw StaffMemberNotFound::withId($staffMemberId);
        }

        return (int) $key;
    }
}
