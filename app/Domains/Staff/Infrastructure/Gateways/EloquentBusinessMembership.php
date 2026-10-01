<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Staff\Contracts\TeamAllowance;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use App\Shared\Contracts\BusinessMembership;
use App\Shared\Contracts\BusinessOwnership;
use App\Shared\Contracts\PausedBusinessAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class EloquentBusinessMembership implements BusinessMembership, BusinessOwnership, PausedBusinessAccess
{
    public function __construct(
        private readonly TeamAllowance $allowance,
    ) {}

    /**
     * @return list<string>
     */
    public function businessIdsFor(string $accountId): array
    {
        $memberships = $this->accessGrantingMembershipsOf($accountId);
        $paused = array_flip($this->pausedAmong($memberships));

        return array_values(array_filter(
            array_keys($memberships),
            static fn (string $businessId): bool => ! isset($paused[$businessId]),
        ));
    }

    public function ownsOpenBusiness(string $accountId): bool
    {
        return in_array(true, $this->accessGrantingMembershipsOf($accountId), strict: true);
    }

    /**
     * @return list<string>
     */
    public function pausedBusinessIdsFor(string $accountId): array
    {
        return $this->pausedAmong($this->accessGrantingMembershipsOf($accountId));
    }

    /**
     * @param  array<string, bool>  $memberships
     * @return list<string>
     */
    private function pausedAmong(array $memberships): array
    {
        $notOwned = array_keys(array_filter(
            $memberships,
            static fn (bool $ownsBusiness): bool => ! $ownsBusiness,
        ));

        if ($notOwned === []) {
            return [];
        }

        $includingTeam = array_flip($this->allowance->businessesIncludingTeam($notOwned));

        return array_values(array_filter(
            $notOwned,
            static fn (string $businessId): bool => ! isset($includingTeam[$businessId]),
        ));
    }

    /**
     * @return array<string, bool>
     */
    private function accessGrantingMembershipsOf(string $accountId): array
    {
        $assignments = StaffRoleAssignments::ASSIGNMENTS_TABLE;
        $roles = StaffRoleAssignments::ROLES_TABLE;
        $team = StaffRoleAssignments::TEAM_COLUMN;

        $roleNames = StaffMemberModel::query()
            ->join('businesses', 'businesses.id', '=', 'staff_members.business_id')
            ->join('users', 'users.id', '=', 'staff_members.account_id')
            ->leftJoin($assignments, function (JoinClause $join) use ($assignments, $team): void {
                $join->on($assignments.'.model_id', '=', 'users.id')
                    ->on($assignments.'.'.$team, '=', 'staff_members.business_id')
                    ->where($assignments.'.model_type', (new User)->getMorphClass());
            })
            ->leftJoin($roles, $roles.'.id', '=', $assignments.'.role_id')
            ->whereNull('businesses.deleted_at')
            ->where('users.uuid', $accountId)
            ->where(static function (Builder $grantingAccess) use ($roles): void {
                $grantingAccess->whereNull($roles.'.name')
                    ->orWhere($roles.'.name', '<>', StaffRole::NoAccess->value);
            })
            ->orderByRaw('case '.$roles.'.name when ? then 0 else 1 end', [StaffRole::Owner->value])
            ->orderBy('staff_members.created_at')
            ->pluck($roles.'.name', 'businesses.uuid');

        $memberships = [];

        foreach ($roleNames as $businessId => $roleName) {
            $memberships[(string) $businessId] = $roleName === StaffRole::Owner->value;
        }

        return $memberships;
    }
}
