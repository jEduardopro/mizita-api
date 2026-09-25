<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class EloquentBusinessMembership implements BusinessMembership
{
    /**
     * @return list<string>
     */
    public function businessIdsFor(string $accountId): array
    {
        $assignments = StaffRoleAssignments::ASSIGNMENTS_TABLE;
        $roles = StaffRoleAssignments::ROLES_TABLE;
        $team = StaffRoleAssignments::TEAM_COLUMN;

        /** @var list<string> $businessIds */
        $businessIds = StaffMemberModel::query()
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
            ->pluck('businesses.uuid')
            ->all();

        return $businessIds;
    }
}
