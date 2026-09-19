<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Infrastructure\Eloquent;

use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Exceptions\BookingPolicyNotFound;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Mappers\BookingPolicyMapper;
use App\Domains\BookingPolicies\Infrastructure\Eloquent\Models\BookingPolicyModel;
use App\Shared\Contracts\BusinessTeamKey;

final class EloquentBookingPolicyRepository implements BookingPolicyRepository
{
    public function __construct(
        private readonly BookingPolicyMapper $mapper,
        private readonly BusinessTeamKey $teamKeys,
    ) {}

    public function findForBusiness(string $businessId): ?BookingPolicy
    {
        $model = BookingPolicyModel::query()
            ->where('business_id', $this->teamKeys->teamKeyFor($businessId))
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    public function save(BookingPolicy $policy): void
    {
        BookingPolicyModel::query()->updateOrCreate(
            ['uuid' => $policy->id],
            $this->mapper->toAttributes($policy, $this->teamKeys->teamKeyFor($policy->businessId)),
        );
    }

    public function delete(string $id): void
    {
        $model = BookingPolicyModel::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw BookingPolicyNotFound::withId($id);
        }

        $model->delete();
    }
}
