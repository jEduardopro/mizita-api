<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Translates between StaffRole and the Spatie role a user holds at one
 * business.
 *
 * It exists so that exactly one class knows the package is here. The domain
 * and application layers speak StaffRole; the repository and the membership
 * gateway speak to this; the pivot table and the team key are named nowhere
 * else in the domain.
 *
 * Roles are matched by name, which is StaffRole's own value, so the seeded
 * primary keys stay a detail of the seeder and the index that needs them.
 *
 * Every write goes through withTeam(), because Spatie reads the current team
 * id from a registrar that lives for the whole request: setting it and walking
 * away would re-scope role checks the caller makes later.
 */
final class StaffRoleAssignments
{
    /**
     * Mirrors config/permission.php: table_names.roles, table_names.
     * model_has_roles and column_names.team_foreign_key. Named here so the
     * ordering join in EloquentBusinessMembership does not spell them out a
     * second time.
     */
    public const ASSIGNMENTS_TABLE = 'model_has_roles';

    public const ROLES_TABLE = 'roles';

    public const TEAM_COLUMN = 'business_id';

    /**
     * Gives the account this role at this business, replacing whatever role it
     * held there before.
     *
     * Sync rather than assign, so a membership that changes role does not keep
     * the permissions of the old one. With teams on, the sync reaches only the
     * roles held at this business.
     */
    public function assign(User $account, int $businessKey, StaffRole $role): void
    {
        $this->withTeam($businessKey, static fn () => $account->syncRoles($role->value));
    }

    /**
     * The role this account holds at this business, or null when it holds none.
     *
     * A name that is not one of StaffRole's raises: a role row nobody can name
     * is a broken seed, and guessing which one was meant would hide it.
     */
    public function roleFor(User $account, int $businessKey): ?StaffRole
    {
        $name = $this->assignmentsOf($account)
            ->where(self::ASSIGNMENTS_TABLE.'.'.self::TEAM_COLUMN, $businessKey)
            ->value(self::ROLES_TABLE.'.name');

        if ($name === null) {
            return null;
        }

        return StaffRole::from((string) $name);
    }

    /**
     * Whether this account holds the owner role at any business at all.
     *
     * Queried straight off the pivot rather than through Spatie's API, because
     * the question is deliberately unscoped: the rule it answers - one owned
     * business per account - is platform-wide, while every Spatie read is
     * scoped to the current team.
     */
    public function ownsAnyBusiness(User $account): bool
    {
        return $this->assignmentsOf($account)
            ->where(self::ROLES_TABLE.'.name', StaffRole::Owner->value)
            ->exists();
    }

    /**
     * Every role row this account holds, in any business, with the role joined
     * so callers can read its name.
     */
    private function assignmentsOf(User $account): Builder
    {
        return DB::table(self::ASSIGNMENTS_TABLE)
            ->join(
                self::ROLES_TABLE,
                self::ROLES_TABLE.'.id',
                '=',
                self::ASSIGNMENTS_TABLE.'.role_id',
            )
            ->where(self::ASSIGNMENTS_TABLE.'.model_type', $account->getMorphClass())
            ->where(self::ASSIGNMENTS_TABLE.'.model_id', $account->getKey());
    }

    /**
     * Runs the work with the business as Spatie's current team, then puts back
     * whatever team was current before.
     */
    private function withTeam(int $businessKey, callable $work): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($businessKey);

        try {
            $work();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
