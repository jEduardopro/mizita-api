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

/**
 * The only place a membership is put back together from its two halves: the row
 * saying the account may operate the business, and the role saying what it may
 * do there.
 */
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

            // The membership fact first, then what it may do. Both writes are
            // in one call so the two halves cannot drift, and both are inside
            // the caller's transaction, so a failure here takes the row with it.
            $this->roles->assign($account, $businessKey, $member->role());
        } catch (UniqueConstraintViolationException $violation) {
            // Either the membership index or the single-owner index on
            // model_has_roles rejected this, and for the writes that reach here
            // both mean the same thing: this account already holds what is
            // being asked for.
            throw AccountAlreadyOwnsBusiness::forAccount($member->accountId, $violation);
        }
    }

    public function findById(string $id): StaffMember
    {
        // Both neighbours are eager loaded because rehydrating the entity needs
        // their uuids: fetching them lazily would be two more queries.
        $model = StaffMemberModel::query()
            ->with(['business', 'account'])
            ->where('uuid', $id)
            ->first();

        $business = $model?->business;
        $account = $model?->account;

        // A membership whose business or account is gone cannot be rehydrated,
        // and is not a membership anybody can act on. The foreign keys make it
        // unreachable short of a soft deleted business.
        if ($model === null || $business === null || $account === null) {
            throw StaffMemberNotFound::withId($id);
        }

        $role = $this->roles->roleFor($account, (int) $model->business_id);

        // Half a membership is not a membership. Every write through save()
        // assigns a role, so a row without one was written around this adapter.
        if ($role === null) {
            throw StaffMemberNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $business->uuid, $account->uuid, $role);
    }

    public function ownsAnyBusiness(string $accountId): bool
    {
        // Deliberately unscoped: one owned business per account is a
        // platform-wide rule, so the question is asked across every team.
        return $this->roles->ownsAnyBusiness($this->accountFor($accountId));
    }

    /** The int key both the foreign key and Spatie's team column point at. */
    private function businessKey(string $businessId): int
    {
        $key = BusinessModel::query()->where('uuid', $businessId)->value('id');

        if ($key === null) {
            // Not a domain failure: every caller registers a membership for a
            // business it has just written, so a miss here is a bug, and a bug
            // deserves a 500 rather than a tidy error code.
            throw (new ModelNotFoundException)->setModel(BusinessModel::class, [$businessId]);
        }

        return (int) $key;
    }

    /** A model rather than a key, because the role is attached to the account itself. */
    private function accountFor(string $accountId): User
    {
        $account = User::query()->where('uuid', $accountId)->first();

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$accountId]);
        }

        return $account;
    }
}
