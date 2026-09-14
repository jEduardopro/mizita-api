<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = Guard::getDefaultName(User::class);

        $permissionIds = $this->syncPermissions($guard);

        foreach ($this->catalogue('roles') as $name => $definition) {
            if ($definition['template'] === true) {
                continue;
            }

            $roleId = $this->syncGlobalRole((string) $name, $definition, $guard);

            $this->grant($roleId, $this->permissionIdsFor($definition, $permissionIds));
        }

        $this->syncRoleIdSequence();

        $registrar->forgetCachedPermissions();
    }

    /**
     * @return array<string, int>
     */
    private function syncPermissions(string $guard): array
    {
        $table = $this->table('permissions');
        $now = now();

        $rows = [];

        foreach ($this->catalogue('permissions') as $name => $definition) {
            $rows[] = [
                'name' => (string) $name,
                'guard_name' => $guard,
                'slug' => $this->slugFor((string) $name),
                'description' => $this->descriptionKeyFor('permissions', (string) $name),
                'scope' => $this->scopeOf($definition)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table($table)->upsert($rows, ['name', 'guard_name'], ['slug', 'description', 'scope', 'updated_at']);

        /** @var array<string, int> $ids */
        $ids = DB::table($table)
            ->where('guard_name', $guard)
            ->whereIn('name', array_column($rows, 'name'))
            ->pluck('id', 'name')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncGlobalRole(string $name, array $definition, string $guard): int
    {
        $attributes = [
            'slug' => $this->slugFor($name),
            'description' => $this->descriptionKeyFor('roles', $name),
            'scope' => $this->scopeOf($definition)->value,
        ];

        $existing = Role::query()
            ->whereNull($this->teamColumn())
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->first();

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return (int) $existing->getKey();
        }

        $created = new Role([...$attributes, 'name' => $name, 'guard_name' => $guard]);

        if (isset($definition['id'])) {
            $created->id = (int) $definition['id'];
        }

        $created->save();

        return (int) $created->getKey();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, int>  $permissionIds
     * @return list<int>
     */
    private function permissionIdsFor(array $definition, array $permissionIds): array
    {
        $granted = $definition['permissions'] ?? [];

        if ($granted === '*') {
            $scope = $this->scopeOf($definition);

            $granted = array_keys(array_filter(
                $this->catalogue('permissions'),
                fn (array $permission): bool => $this->scopeOf($permission) === $scope,
            ));
        }

        return array_map(
            static function (string $name) use ($permissionIds): int {
                if (! isset($permissionIds[$name])) {
                    throw new RuntimeException(
                        "config/authorization.php grants the unknown permission [{$name}]."
                    );
                }

                return $permissionIds[$name];
            },
            array_values((array) $granted),
        );
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function grant(int $roleId, array $permissionIds): void
    {
        if ($permissionIds === []) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);

        DB::table($this->table('role_has_permissions'))->insertOrIgnore(array_map(
            static fn (int $permissionId): array => [
                $registrar->pivotPermission => $permissionId,
                $registrar->pivotRole => $roleId,
            ],
            $permissionIds,
        ));
    }

    private function syncRoleIdSequence(): void
    {
        $table = $this->table('roles');

        DB::statement(
            "select setval(pg_get_serial_sequence('{$table}', 'id'), (select max(id) from {$table}))"
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function scopeOf(array $definition): AuthorizationScope
    {
        return $definition['scope'];
    }

    private function slugFor(string $name): string
    {
        return str_replace('.', '-', $name);
    }

    private function descriptionKeyFor(string $namespace, string $name): string
    {
        return $namespace.'.'.$name.'.description';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogue(string $section): array
    {
        return config('authorization.'.$section);
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }

    private function teamColumn(): string
    {
        return (string) config('permission.column_names.team_foreign_key');
    }
}
