<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Infrastructure\Eloquent\Mappers\StaffMemberMapper;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentStaffMemberRepository implements StaffMemberRepository
{
    public function __construct(
        private readonly StaffMemberMapper $mapper,
        private readonly StaffRoleAssignments $roles,
    ) {}

    public function save(StaffMember $member): void
    {
        $account = $this->accountFor($member->accountId);
        $businessKey = $this->businessKey($member->businessId);

        try {
            StaffMemberModel::query()->updateOrCreate(
                ['uuid' => $member->id],
                $this->mapper->toAttributes($member, $businessKey, $account->getKey()),
            );

            $this->roles->assign($account, $businessKey, $member->role());
        } catch (UniqueConstraintViolationException $violation) {
            throw AccountAlreadyOwnsBusiness::forAccount($member->accountId, $violation);
        }
    }

    public function findById(string $id): StaffMember
    {
        $model = StaffMemberModel::query()
            ->with(['business', 'account'])
            ->where('uuid', $id)
            ->first();

        $business = $model?->business;
        $account = $model?->account;

        if ($model === null || $business === null || $account === null) {
            throw StaffMemberNotFound::withId($id);
        }

        $role = $this->roles->roleFor($account, (int) $model->business_id);

        if ($role === null) {
            throw StaffMemberNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $business->uuid, $account->uuid, $role);
    }

    public function ownsAnyBusiness(string $accountId): bool
    {
        return $this->roles->ownsAnyBusiness($this->accountFor($accountId));
    }

    private function businessKey(string $businessId): int
    {
        $key = BusinessModel::query()->where('uuid', $businessId)->value('id');

        if ($key === null) {
            throw (new ModelNotFoundException)->setModel(BusinessModel::class, [$businessId]);
        }

        return (int) $key;
    }

    private function accountFor(string $accountId): User
    {
        $account = User::query()->where('uuid', $accountId)->first();

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$accountId]);
        }

        return $account;
    }
}
