<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Database\Query\JoinClause;

/**
 * Answers which businesses an account may operate, straight from the
 * membership table.
 *
 * This is the platform's tenant resolver, and the one query in it has to run
 * with nothing resolved yet: no business context, and no Spatie team id. That
 * is why the role is reached by joining the assignment tables rather than
 * through Spatie's API, every read of which is scoped to the current team -
 * the team here is precisely what is being looked for.
 *
 * A gateway rather than a repository method: the port belongs to the shared
 * kernel, is consumed by code that must not import Staff, and answers in
 * uuids instead of entities.
 */
final class EloquentBusinessMembership implements BusinessMembership
{
    /**
     * One query, joined rather than issued per relation, because it runs on
     * every authenticated request that needs a tenant.
     *
     * Ordered owner first and then by membership age, so a caller taking the
     * head of the list gets the business they registered. The owner test is a
     * left join: a membership whose role assignment is missing still resolves,
     * it just sorts last, because failing to resolve a tenant is a worse
     * outcome than resolving them in an unexpected order.
     *
     * Soft deleted memberships are excluded by the model's scope, soft deleted
     * businesses by the join condition: neither is operable.
     *
     * @return list<string> business uuids
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
            ->orderByRaw('case '.$roles.'.name when ? then 0 else 1 end', [StaffRole::Owner->value])
            ->orderBy('staff_members.created_at')
            ->pluck('businesses.uuid')
            ->all();

        return $businessIds;
    }
}
