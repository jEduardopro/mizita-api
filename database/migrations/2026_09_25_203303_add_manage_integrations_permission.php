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
    private const INTRODUCED_PERMISSION = 'manage_integrations';

    private const STAFF_ROLE = 'staff';

    private const ROWS_PER_INSERT = 1000;

    public function up(): void
    {
        $guard = Guard::getDefaultName(User::class);

        $this->introducePermission($guard);

        $permissionId = $this->permissionIdNamed(self::INTRODUCED_PERMISSION, $guard);

        if ($permissionId === null) {
            return;
        }

        $this->grantToGlobalOwner($permissionId);
        $this->grant($this->businessRoleIdsNamed(self::STAFF_ROLE, $guard), $permissionId);

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $guard = Guard::getDefaultName(User::class);
        $permissionId = $this->permissionIdNamed(self::INTRODUCED_PERMISSION, $guard);

        if ($permissionId === null) {
            return;
        }

        $pivotPermission = app(PermissionRegistrar::class)->pivotPermission;

        DB::table($this->table('role_has_permissions'))->where($pivotPermission, $permissionId)->delete();
        DB::table($this->table('model_has_permissions'))->where($pivotPermission, $permissionId)->delete();
        DB::table($this->table('permissions'))->where('id', $permissionId)->delete();

        $this->forgetPermissionCache();
    }

    private function introducePermission(string $guard): void
    {
        $now = now();

        DB::table($this->table('permissions'))->insertOrIgnore([
            'name' => self::INTRODUCED_PERMISSION,
            'guard_name' => $guard,
            'slug' => AuthorizationNaming::slugFor(self::INTRODUCED_PERMISSION),
            'description' => AuthorizationNaming::descriptionKeyFor('permissions', self::INTRODUCED_PERMISSION),
            'scope' => AuthorizationScope::Business->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function grantToGlobalOwner(int $permissionId): void
    {
        $ownerExists = DB::table($this->table('roles'))->where('id', SeededStaffRole::OWNER_ID)->exists();

        if (! $ownerExists) {
            return;
        }

        $this->grant([SeededStaffRole::OWNER_ID], $permissionId);
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function grant(array $roleIds, int $permissionId): void
    {
        $registrar = app(PermissionRegistrar::class);

        $rows = array_map(
            static fn (int $roleId): array => [
                $registrar->pivotRole => $roleId,
                $registrar->pivotPermission => $permissionId,
            ],
            $roleIds,
        );

        foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
            DB::table($this->table('role_has_permissions'))->insertOrIgnore($chunk);
        }
    }

    private function permissionIdNamed(string $name, string $guard): ?int
    {
        $id = DB::table($this->table('permissions'))
            ->where('guard_name', $guard)
            ->where('name', $name)
            ->value('id');

        return $id === null ? null : (int) $id;
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
