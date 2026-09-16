<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Models\User;
use App\Shared\Infrastructure\Authorization\AuthorizationNaming;
use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class BusinessRoleTemplates
{
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
                    'slug' => AuthorizationNaming::slugFor((string) $name),
                    'description' => AuthorizationNaming::descriptionKeyFor('roles', (string) $name),
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
