<?php

declare(strict_types=1);

use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the three columns the access model needs and Spatie does not ship.
 *
 * - description is a translation KEY, never a sentence. Labels live in
 *   lang/<locale>/{roles,permissions}.php, at the cost of no referential
 *   integrity between the two, which a unit test covers.
 * - scope is a security boundary: it keeps a business role from ever being
 *   handed a platform permission. All three columns are deliberately left OUT of
 *   config/permission.php's cache.column_names_except - a cached Permission
 *   whose scope came back null would defeat the boundary quietly.
 *
 * Postgres treats NULLs as distinct, so the unique the package ships -
 * (business_id, name, guard_name) - does not stop two global roles sharing a
 * name. The two partial uniques close that. roles.slug cannot be globally unique
 * in turn, because every business owns a clone of a template role and every
 * clone carries the same name and slug.
 *
 * down() drops what it added and nothing else: a schema rollback that deleted a
 * business's access model would be a different and much worse operation.
 */
return new class extends Migration
{
    /**
     * Both halves of the migration walk this, so the two tables cannot drift apart.
     *
     * @var array<string, string>
     */
    private const DESCRIPTION_NAMESPACES = [
        'permissions' => 'permissions',
        'roles' => 'roles',
    ];

    private const PERMISSION_SLUG_INDEX = 'permissions_slug_guard_name_unique';

    private const ROLE_SLUG_INDEX = 'roles_business_id_slug_guard_name_unique';

    private const GLOBAL_ROLE_NAME_INDEX = 'roles_global_name_unique';

    private const GLOBAL_ROLE_SLUG_INDEX = 'roles_global_slug_unique';

    public function up(): void
    {
        $this->refuseDuplicateGlobalRoles();

        foreach (self::DESCRIPTION_NAMESPACES as $configKey => $namespace) {
            $table = $this->table($configKey);

            Schema::table($table, static function (Blueprint $blueprint): void {
                // Nullable first, because the rows that already exist have no
                // value to give. The backfill below is what makes NOT NULL
                // possible a statement later.
                $blueprint->string('slug')->nullable();
                $blueprint->string('description')->nullable();
                $blueprint->string('scope', 16)->nullable();
            });

            $this->backfill($table, $namespace);

            foreach (['slug', 'description', 'scope'] as $column) {
                DB::statement("alter table {$table} alter column {$column} set not null");
            }

            DB::statement(
                "alter table {$table} add constraint {$table}_scope_check ".
                'check (scope in ('.$this->allowedScopes().'))'
            );
        }

        $this->createIndexes();
    }

    public function down(): void
    {
        foreach ([
            self::GLOBAL_ROLE_SLUG_INDEX,
            self::GLOBAL_ROLE_NAME_INDEX,
            self::ROLE_SLUG_INDEX,
            self::PERMISSION_SLUG_INDEX,
        ] as $index) {
            DB::statement('drop index if exists '.$index);
        }

        foreach (array_keys(self::DESCRIPTION_NAMESPACES) as $configKey) {
            $table = $this->table($configKey);

            DB::statement("alter table {$table} drop constraint if exists {$table}_scope_check");

            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['slug', 'description', 'scope']);
            });
        }
    }

    /**
     * Every existing row is business scoped - the platform plane does not exist
     * yet - so the scope is a constant rather than something to guess at.
     */
    private function backfill(string $table, string $namespace): void
    {
        DB::statement(
            "update {$table} set".
            " slug = replace(name, '.', '-'),".
            " description = ? || name || '.description',".
            ' scope = ?',
            [$namespace.'.', AuthorizationScope::Business->value],
        );
    }

    private function createIndexes(): void
    {
        $permissions = $this->table('permissions');
        $roles = $this->table('roles');
        $team = $this->teamColumn();

        DB::statement(
            'create unique index '.self::PERMISSION_SLUG_INDEX.
            " on {$permissions} (slug, guard_name)"
        );

        // Per team, mirroring the package's own unique on the name. Rows with a
        // null team are not constrained by this one at all, which is what the
        // two partial indexes below are for.
        DB::statement(
            'create unique index '.self::ROLE_SLUG_INDEX.
            " on {$roles} ({$team}, slug, guard_name)"
        );

        DB::statement(
            'create unique index '.self::GLOBAL_ROLE_NAME_INDEX.
            " on {$roles} (name, guard_name) where {$team} is null"
        );

        DB::statement(
            'create unique index '.self::GLOBAL_ROLE_SLUG_INDEX.
            " on {$roles} (slug, guard_name) where {$team} is null"
        );
    }

    /**
     * Stops while the ambiguity the index cannot be added over is still visible,
     * naming the rows rather than failing on a constraint violation whose
     * message says nothing about which roles are involved.
     */
    private function refuseDuplicateGlobalRoles(): void
    {
        $roles = $this->table('roles');
        $team = $this->teamColumn();

        /** @var array<int, object{name: string, guard_name: string, total: int}> $duplicates */
        $duplicates = DB::select(
            "select name, guard_name, count(*) as total from {$roles}".
            " where {$team} is null group by name, guard_name having count(*) > 1"
        );

        if ($duplicates === []) {
            return;
        }

        $rows = implode(', ', array_map(
            static fn (object $row): string => "{$row->name}/{$row->guard_name} x{$row->total}",
            $duplicates,
        ));

        throw new RuntimeException(
            "Cannot add the global role uniques: duplicate global roles exist [{$rows}]. ".
            'Merge them and run the migration again.'
        );
    }

    private function allowedScopes(): string
    {
        return implode(', ', array_map(
            static fn (AuthorizationScope $scope): string => "'".$scope->value."'",
            AuthorizationScope::cases(),
        ));
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
