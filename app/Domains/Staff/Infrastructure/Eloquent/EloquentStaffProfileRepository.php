<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent;

use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\BookingSlugAlreadyTaken;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;
use App\Domains\Staff\Infrastructure\Eloquent\Mappers\StaffProfileMapper;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentStaffProfileRepository implements BookingSlugRegistry, StaffProfileRepository
{
    private const STAFF_MEMBERS_TABLE = 'staff_members';

    private const BOOKING_SLUG_UNIQUE_INDEX = 'staff_profiles_business_booking_slug_unique';

    private const SUFFIXED_PREFIX_OF_BASE = "booking_slug ~ '-[0-9]+\$' "
        ."and starts_with(cast(? as text), regexp_replace(booking_slug, '-[0-9]+\$', ''))";

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

        try {
            StaffProfileModel::query()->updateOrCreate(
                ['uuid' => $profile->id],
                $this->mapper->toAttributes(
                    $profile,
                    $businessKey,
                    $this->staffMemberKey($businessKey, $profile->staffMemberId),
                ),
            );
        } catch (UniqueConstraintViolationException $violation) {
            $this->failFrom($profile, $violation);
        }
    }

    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array
    {
        return $this->ofBusinessKey($this->businessKeys->teamKeyFor($businessId))
            ->where(static function (Builder $query) use ($base): void {
                $query->where('booking_slug', $base)
                    ->orWhereRaw(self::SUFFIXED_PREFIX_OF_BASE, [$base]);
            })
            ->pluck('booking_slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->values()
            ->all();
    }

    public function isHeldByAnother(string $businessId, string $bookingSlug, string $staffMemberId): bool
    {
        $businessKey = $this->businessKeys->teamKeyFor($businessId);

        return $this->ofBusinessKey($businessKey)
            ->where('booking_slug', $bookingSlug)
            ->whereNotIn(
                'staff_member_id',
                static fn (QueryBuilder $query) => $query
                    ->select('id')
                    ->from(self::STAFF_MEMBERS_TABLE)
                    ->where('business_id', $businessKey)
                    ->where('uuid', $staffMemberId),
            )
            ->exists();
    }

    public function staffMemberIdFor(string $businessId, string $bookingSlug): string
    {
        $staffMemberId = $this->ofBusinessKey($this->businessKeys->teamKeyFor($businessId))
            ->with('staffMember:id,uuid')
            ->where('booking_slug', $bookingSlug)
            ->first()
            ?->staffMember
            ?->uuid;

        if ($staffMemberId === null) {
            throw StaffMemberNotFound::withBookingSlug($bookingSlug);
        }

        return (string) $staffMemberId;
    }

    /**
     * @return Builder<StaffProfileModel>
     */
    private function ofBusinessKey(int $businessKey): Builder
    {
        return StaffProfileModel::query()->where('business_id', $businessKey);
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

    private function failFrom(StaffProfile $profile, UniqueConstraintViolationException $violation): never
    {
        $bookingSlug = $profile->bookingSlug();

        if ($bookingSlug !== null && str_contains($violation->getMessage(), self::BOOKING_SLUG_UNIQUE_INDEX)) {
            throw BookingSlugAlreadyTaken::for($bookingSlug->value, $violation);
        }

        throw $violation;
    }
}
