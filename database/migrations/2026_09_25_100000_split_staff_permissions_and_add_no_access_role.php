<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Models\User;
use App\Shared\Infrastructure\Authorization\AuthorizationNaming;
use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const RETIRED_PERMISSION = 'manage_staff';

    private const INTRODUCED_PERMISSIONS = [
        'view_staff_members',
        'create_staff_member',
        'edit_staff_member',
        'delete_staff_member',
        'manage_all_calendars',
    ];

    private const STAFF_ROLE = 'staff';

    private const STAFF_STARTING_PERMISSIONS = [
        'view_services',
        'view_appointments',
        'create_appointment',
        'edit_appointment',
        'delete_appointment',
        'view_customers',
        'create_customer',
        'edit_customer',
        'view_payments',
        'create_payment',
    ];

    private const NO_ACCESS_ROLE = 'no_access';

    private const ROWS_PER_INSERT = 1000;

    public function up(): void
    {
        $guard = Guard::getDefaultName(User::class);

        $this->retirePermission($guard);
        $this->introducePermissions($guard);

        $this->grantToGlobalOwner($this->permissionIdsNamed(self::INTRODUCED_PERMISSIONS, $guard));
        $this->cloneNoAccessForBusinessesWithout($guard);
        $this->grant(
            $this->businessRoleIdsNamed(self::STAFF_ROLE, $guard),
            $this->permissionIdsNamed(self::STAFF_STARTING_PERMISSIONS, $guard),
        );

        $this->syncRoleIdSequence();
        $this->forgetPermissionCache();
    }

    // Deliberately empty: restoring manage_staff would re-grant a permission no route checks any more.
    public function down(): void {}

    private function retirePermission(string $guard): void
    {
        $permissionId = DB::table($this->table('permissions'))
            ->where('name', self::RETIRED_PERMISSION)
            ->where('guard_name', $guard)
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $pivotPermission = app(PermissionRegistrar::class)->pivotPermission;

        DB::table($this->table('role_has_permissions'))->where($pivotPermission, $permissionId)->delete();
        DB::table($this->table('model_has_permissions'))->where($pivotPermission, $permissionId)->delete();
        DB::table($this->table('permissions'))->where('id', $permissionId)->delete();
    }

    private function introducePermissions(string $guard): void
    {
        $now = now();

        DB::table($this->table('permissions'))->insertOrIgnore(array_map(
            static fn (string $name): array => [
                'name' => $name,
                'guard_name' => $guard,
                'slug' => AuthorizationNaming::slugFor($name),
                'description' => AuthorizationNaming::descriptionKeyFor('permissions', $name),
                'scope' => AuthorizationScope::Business->value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::INTRODUCED_PERMISSIONS,
        ));
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function grantToGlobalOwner(array $permissionIds): void
    {
        $ownerExists = DB::table($this->table('roles'))->where('id', SeededStaffRole::OWNER_ID)->exists();

        if (! $ownerExists) {
            return;
        }

        $this->grant([SeededStaffRole::OWNER_ID], $permissionIds);
    }

    private function cloneNoAccessForBusinessesWithout(string $guard): void
    {
        $roles = $this->table('roles');
        $team = $this->teamColumn();

        DB::statement(
            "insert into {$roles} ({$team}, name, guard_name, slug, description, scope, created_at, updated_at)".
            ' select b.id, ?, ?, ?, ?, ?, now(), now() from businesses b'.
            " where not exists (select 1 from {$roles} r".
            "     where r.{$team} = b.id and r.name = ? and r.guard_name = ?)",
            [
                self::NO_ACCESS_ROLE,
                $guard,
                AuthorizationNaming::slugFor(self::NO_ACCESS_ROLE),
                AuthorizationNaming::descriptionKeyFor('roles', self::NO_ACCESS_ROLE),
                AuthorizationScope::Business->value,
                self::NO_ACCESS_ROLE,
                $guard,
            ],
        );
    }

    /**
     * @param  list<int>  $roleIds
     * @param  list<int>  $permissionIds
     */
    private function grant(array $roleIds, array $permissionIds): void
    {
        if ($roleIds === [] || $permissionIds === []) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [$registrar->pivotRole => $roleId, $registrar->pivotPermission => $permissionId];
            }
        }

        foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
            DB::table($this->table('role_has_permissions'))->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function permissionIdsNamed(array $names, string $guard): array
    {
        return DB::table($this->table('permissions'))
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function businessRoleIdsNamed(string $name, string $guard): array
    {
        return DB::table($this->table('roles'))
            ->whereNotNull($this->teamColumn())
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function syncRoleIdSequence(): void
    {
        $roles = $this->table('roles');

        DB::statement(
            "select setval(pg_get_serial_sequence('{$roles}', 'id'), (select max(id) from {$roles}))"
        );
    }

    private function forgetPermissionCache(): void
    {
        $store = config('permission.cache.store');

        app('cache')
            ->store($store !== 'default' ? $store : null)
            ->forget(config('permission.cache.key'));
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }

    private function teamColumn(): string
    {
        return (string) config('permission.column_names.team_foreign_key');
    }
};
