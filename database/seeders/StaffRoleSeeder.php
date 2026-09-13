<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the two membership roles and the permissions that separate them.
 *
 * A seeder rather than a migration: this is reference data, and a migration
 * that seeds cannot be re-run when the catalogue grows.
 *
 * Idempotent and additive. Rows are matched on their primary key, so re-running
 * never duplicates, and permissions are granted rather than synced, so nothing
 * an operator attached by hand is revoked behind their back.
 *
 * The primary keys are fixed on purpose and must stay as they are: the partial
 * unique index that keeps an account to a single owner role names
 * SeededStaffRole::OWNER_ID literally, because an index predicate cannot look a
 * role up by name. Give the owner role a different id and that guarantee
 * quietly stops applying.
 *
 * Both roles are global - no team - so one definition serves every business,
 * while the assignment in model_has_roles carries the business the role is held
 * at.
 *
 * The permission list is deliberately two lines long. Inviting staff and
 * editing the business are exactly what distinguishes an owner from a staff
 * member, and both are decided work. Permissions for services, appointments or
 * availability are not listed because those domains do not exist yet: the list
 * grows one line at a time as each feature lands, and a taxonomy invented ahead
 * of the features would be scaffolding nobody can check against anything.
 */
final class StaffRoleSeeder extends Seeder
{
    /**
     * Granted to owner, withheld from staff.
     *
     * @var list<string>
     */
    private const OWNER_PERMISSIONS = [
        'staff.manage',
        'business.manage',
    ];

    public function run(): void
    {
        // Spatie serves its registry from cache, so rows written here would be
        // invisible to anything checking permissions later in the same process.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Derived from the model the roles are held by, so the guard on these
        // rows is by construction the one a permission check looks under.
        $guard = Guard::getDefaultName(User::class);

        $owner = $this->role(StaffRole::Owner, SeededStaffRole::OWNER_ID, $guard);
        $owner->givePermissionTo($this->ownerPermissions($guard));

        // Created with nothing attached. The role still has to exist: every
        // membership is assigned one on save, and an unknown name would fail.
        $this->role(StaffRole::Member, SeededStaffRole::MEMBER_ID, $guard);

        $this->syncRoleIdSequence();
    }

    /**
     * @return list<Permission>
     */
    private function ownerPermissions(string $guard): array
    {
        return array_map(
            static fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]),
            self::OWNER_PERMISSIONS,
        );
    }

    /**
     * Matched on the id rather than the name, because the id is the half of
     * this row another guarantee depends on.
     */
    private function role(StaffRole $role, int $id, string $guard): Role
    {
        $existing = Role::query()->find($id);

        if ($existing !== null) {
            return $existing;
        }

        $created = new Role(['name' => $role->value, 'guard_name' => $guard]);

        // Assigned rather than mass assigned: Spatie guards the primary key on
        // its models, so passing it to create() would drop it silently and
        // leave the id to the sequence.
        $created->id = $id;
        $created->save();

        return $created;
    }

    /**
     * Moves the roles sequence past the ids written by hand above, so the next
     * role created the ordinary way does not collide with one of them.
     */
    private function syncRoleIdSequence(): void
    {
        $table = (new Role)->getTable();

        DB::statement(
            "select setval(pg_get_serial_sequence('{$table}', 'id'), (select max(id) from {$table}))"
        );
    }
}
