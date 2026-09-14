<?php

declare(strict_types=1);

use App\Models\User;
use App\Shared\ValueObjects\AuthorizationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;

/**
 * Retires the global template roles that used to stand in for businesses created
 * before roles were cloned per business. That row is the one the design forbids:
 * Role::findByParam() matches "business_id is null or business_id = <team>" with
 * no ORDER BY, so it would attach an undefined one of the two.
 *
 * The three steps must run in this order. Deleting the global rows before the
 * assignments have been moved would cascade somebody's access away, and an
 * assignment that cannot be moved stops the migration rather than being revoked.
 *
 * down() is deliberately empty: re-creating the ambiguous global row and moving
 * live assignments back onto it is not a rollback but a regression.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = $this->templateRoles();

        if ($templates === []) {
            return;
        }

        // Derived exactly as the seeder derives it, so this migration can never
        // move rows the seeder would not have written.
        $guard = Guard::getDefaultName(User::class);

        foreach ($templates as $name => $definition) {
            $created = $this->cloneForBusinessesWithout((string) $name, $definition, $guard);

            $this->grantStartingPermissions($created, $definition, $guard);
        }

        $this->repointAssignmentsOffGlobalTemplates(array_keys($templates), $guard);
        $this->deleteGlobalTemplateRoles(array_keys($templates), $guard);

        $this->syncRoleIdSequence();
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        // See the class docblock: there is nothing safe to undo here.
    }

    /**
     * Every business, soft deleted ones included: an assignment may still point
     * at the global row from a business that has since been closed, and the
     * re-pointing step needs somewhere to move it.
     *
     * @param  array<string, mixed>  $definition
     * @return list<int> the role ids this call created
     */
    private function cloneForBusinessesWithout(string $name, array $definition, string $guard): array
    {
        $roles = $this->table('roles');
        $team = $this->teamColumn();

        /** @var array<int, object{id: int}> $created */
        $created = DB::select(
            "insert into {$roles} ({$team}, name, guard_name, slug, description, scope, created_at, updated_at)".
            ' select b.id, ?, ?, ?, ?, ?, now(), now() from businesses b'.
            " where not exists (select 1 from {$roles} r".
            "     where r.{$team} = b.id and r.name = ? and r.guard_name = ?)".
            ' returning id',
            [
                $name,
                $guard,
                str_replace('.', '-', $name),
                'roles.'.$name.'.description',
                $this->scopeOf($definition)->value,
                $name,
                $guard,
            ],
        );

        return array_map(static fn (object $row): int => (int) $row->id, $created);
    }

    /**
     * Only the clones this run created, deliberately: a clone that was already
     * there belongs to its business, and re-applying a template over it would
     * restore whatever the owner had revoked.
     *
     * @param  list<int>  $roleIds
     * @param  array<string, mixed>  $definition
     */
    private function grantStartingPermissions(array $roleIds, array $definition, string $guard): void
    {
        /** @var list<string> $granted */
        $granted = (array) ($definition['permissions'] ?? []);

        if ($roleIds === [] || $granted === []) {
            return;
        }

        $permissionIds = DB::table($this->table('permissions'))
            ->where('guard_name', $guard)
            ->whereIn('name', $granted)
            ->pluck('id');

        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => (int) $permissionId];
            }
        }

        DB::table($this->table('role_has_permissions'))->insertOrIgnore($rows);
    }

    /**
     * An insert rather than an update because the team column is part of this
     * table's primary key: adding the clone's row and letting the later cascade
     * take the template's away is the same outcome with no in-place key rewrite.
     *
     * @param  list<string>  $names
     */
    private function repointAssignmentsOffGlobalTemplates(array $names, string $guard): void
    {
        $assignments = $this->table('model_has_roles');
        $roles = $this->table('roles');
        $team = $this->teamColumn();
        $morphKey = $this->morphKey();
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        DB::statement(
            "insert into {$assignments} (role_id, model_type, {$morphKey}, {$team})".
            " select clone.id, a.model_type, a.{$morphKey}, a.{$team} from {$assignments} a".
            " join {$roles} template on template.id = a.role_id".
            " join {$roles} clone on clone.{$team} = a.{$team}".
            '     and clone.name = template.name and clone.guard_name = template.guard_name'.
            " where template.{$team} is null and template.name in ({$placeholders})".
            '   and template.guard_name = ?'.
            ' on conflict do nothing',
            [...$names, $guard],
        );

        $this->refuseStrandedAssignments($names, $guard);
    }

    /**
     * @param  list<string>  $names
     */
    private function refuseStrandedAssignments(array $names, string $guard): void
    {
        $assignments = $this->table('model_has_roles');
        $roles = $this->table('roles');
        $team = $this->teamColumn();
        $morphKey = $this->morphKey();
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        /** @var array<int, object{model_type: string, holder: int, business: ?int, name: string}> $stranded */
        $stranded = DB::select(
            "select a.model_type, a.{$morphKey} as holder, a.{$team} as business, template.name".
            " from {$assignments} a join {$roles} template on template.id = a.role_id".
            " where template.{$team} is null and template.name in ({$placeholders})".
            '   and template.guard_name = ?'.
            "   and not exists (select 1 from {$roles} clone".
            "       where clone.{$team} = a.{$team} and clone.name = template.name".
            '         and clone.guard_name = template.guard_name)',
            [...$names, $guard],
        );

        if ($stranded === []) {
            return;
        }

        $rows = implode(', ', array_map(
            static fn (object $row): string => "{$row->model_type}:{$row->holder} at business {$row->business} ({$row->name})",
            $stranded,
        ));

        throw new RuntimeException(
            "Cannot retire the global template roles: no clone exists for [{$rows}]. ".
            'Create the missing business roles and run the migration again.'
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function deleteGlobalTemplateRoles(array $names, string $guard): void
    {
        DB::table($this->table('roles'))
            ->whereNull($this->teamColumn())
            ->whereIn('name', $names)
            ->where('guard_name', $guard)
            ->delete();
    }

    /**
     * Runtime critical, not housekeeping: every clone created from here on takes
     * its id from this sequence inside the transaction that onboards a business,
     * so a sequence left behind by a hand-pinned id breaks signup.
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templateRoles(): array
    {
        /** @var array<string, array<string, mixed>> $roles */
        $roles = config('authorization.roles');

        return array_filter($roles, static fn (array $role): bool => $role['template'] === true);
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

    private function teamColumn(): string
    {
        return (string) config('permission.column_names.team_foreign_key');
    }

    private function morphKey(): string
    {
        return (string) config('permission.column_names.model_morph_key');
    }
};
