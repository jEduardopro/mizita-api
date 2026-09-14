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

/**
 * Writes config/authorization.php into the database: every permission, and only
 * the roles the catalogue marks as NOT templates. A template role never gets a
 * global row - it exists one clone at a time, written by BusinessRoleTemplates.
 *
 * Idempotent and additive: permissions are granted rather than synced, so
 * nothing an operator attached by hand is revoked behind their back.
 *
 * DO NOT "improve" this into something that also syncs the per-business clones.
 * A business's staff role is the business's to edit; a seeder that re-applied
 * the template would wipe every owner's changes on the next deployment, silently
 * and platform-wide.
 */
final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        // Spatie serves its registry from cache, so rows written here would be
        // invisible to anything checking permissions later in the same process.
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Derived from the model the roles are held by, so the guard on these
        // rows is by construction the one a permission check looks under.
        $guard = Guard::getDefaultName(User::class);

        $permissionIds = $this->syncPermissions($guard);

        foreach ($this->catalogue('roles') as $name => $definition) {
            // Template roles have no global row. This is the line that keeps
            // Role::findByParam() unambiguous under every team.
            if ($definition['template'] === true) {
                continue;
            }

            $roleId = $this->syncGlobalRole((string) $name, $definition, $guard);

            $this->grant($roleId, $this->permissionIdsFor($definition, $permissionIds));
        }

        $this->syncRoleIdSequence();

        // The grants above were written through the query builder, so the model
        // events that usually clear the registry never fired.
        $registrar->forgetCachedPermissions();
    }

    /**
     * Through the query builder rather than Permission::findOrCreate(): that
     * helper reads the registrar cache, and it knows nothing of slug,
     * description or scope, so it would neither write them nor refresh them.
     *
     * @return array<string, int> name => id
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

        // Upsert on the package's own unique. created_at is not in the update
        // list, so a row keeps the day it was first seeded.
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
     * Matched on the name within the global plane rather than on the pinned id,
     * because the partial unique index added with the scope column makes that
     * match exactly one row, and because a catalogue entry is free to carry no
     * pinned id at all.
     *
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
            // Assigned rather than mass assigned: Spatie guards the primary key
            // on its models, so passing it to create() would drop it silently
            // and leave the id to the sequence.
            $created->id = (int) $definition['id'];
        }

        $created->save();

        return (int) $created->getKey();
    }

    /**
     * '*' expands only within the role's own scope. That single condition is
     * where "a business owner must never hold a platform permission" lives, so
     * the platform plane can land later without anyone having to remember it.
     *
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

    /**
     * Runtime critical, not housekeeping. Every business's staff clone takes its
     * id from this sequence inside the transaction that onboards the business, so
     * a lagging sequence makes the first clone collide with the owner role's
     * pinned id and breaks signup, not a seeder.
     */
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
