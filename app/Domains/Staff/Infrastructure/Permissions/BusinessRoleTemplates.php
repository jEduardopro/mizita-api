<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Models\User;
use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives a business its own copy of every role the catalogue marks as a template,
 * which is what makes a role editable per business.
 *
 * Runs once, when the business is created, and is deliberately not re-applied:
 * re-running the template over a clone would revoke whatever the owner changed.
 */
final class BusinessRoleTemplates
{
    /**
     * Role::query()->firstOrCreate(), never Role::create(). The static create()
     * runs findByParam(), which with teams on also matches a row whose team is
     * null - so it can throw RoleAlreadyExists against a row that is not the one
     * being created. The query builder skips that lookup entirely, and the
     * unique on (business_id, name, guard_name) makes the create half safe
     * against a concurrent signup.
     *
     * The owner role is excluded by construction rather than by name: it is
     * template => false in the catalogue, so no code path here could clone it.
     * A second owner row would let an account quietly come to own two businesses.
     */
    public function cloneFor(int $businessKey): void
    {
        $guard = Guard::getDefaultName(User::class);

        foreach ($this->templates() as $name => $definition) {
            $role = Role::query()->firstOrCreate(
                [
                    StaffRoleAssignments::TEAM_COLUMN => $businessKey,
                    'name' => $name,
                    'guard_name' => $guard,
                ],
                [
                    'slug' => str_replace('.', '-', $name),
                    'description' => 'roles.'.$name.'.description',
                    'scope' => $this->scopeOf($definition)->value,
                ],
            );

            if ($role->wasRecentlyCreated) {
                $this->grant((int) $role->getKey(), $definition, $guard);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        /** @var array<string, array<string, mixed>> $roles */
        $roles = config('authorization.roles');

        return array_filter($roles, static fn (array $role): bool => $role['template'] === true);
    }

    /**
     * Query builder rather than givePermissionTo(): that helper resolves names
     * through the registrar cache, and this runs inside the transaction that
     * creates the business, where the cache is a liability rather than a saving.
     *
     * '*' is not expanded here, and no template uses it. A template that ever
     * needs it can have it, in the same one place the seeder expands it.
     *
     * @param  array<string, mixed>  $definition
     */
    private function grant(int $roleId, array $definition, string $guard): void
    {
        /** @var list<string> $names */
        $names = (array) ($definition['permissions'] ?? []);

        if ($names === []) {
            return;
        }

        $permissionIds = DB::table($this->table('permissions'))
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->pluck('id');

        $registrar = app(PermissionRegistrar::class);

        DB::table($this->table('role_has_permissions'))->insertOrIgnore(
            $permissionIds
                ->map(static fn (mixed $permissionId): array => [
                    $registrar->pivotPermission => (int) $permissionId,
                    $registrar->pivotRole => $roleId,
                ])
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function scopeOf(array $definition): AuthorizationScope
    {
        return $definition['scope'];
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }
}
