<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class StaffRoleAssignments
{
    public const ASSIGNMENTS_TABLE = 'model_has_roles';

    public const ROLES_TABLE = 'roles';

    public const TEAM_COLUMN = 'business_id';

    public function assign(User $account, int $businessKey, StaffRole $role): void
    {
        $this->withTeam($businessKey, static fn () => $account->syncRoles($role->value));
    }

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

    public function ownsAnyBusiness(User $account): bool
    {
        return $this->assignmentsOf($account)
            ->where(self::ROLES_TABLE.'.name', StaffRole::Owner->value)
            ->exists();
    }

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
