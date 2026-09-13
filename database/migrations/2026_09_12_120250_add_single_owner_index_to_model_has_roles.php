<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SINGLE_OWNER_INDEX = 'model_has_roles_single_owner_unique';

    /**
     * Restores, on Spatie's tables, the guarantee that used to live on
     * staff_members: an account owns one business or none.
     *
     * Read this before changing anything about how roles are seeded. An index
     * predicate cannot look a role up by name, so this one names the owner
     * role's primary key literally. The guarantee therefore depends on
     * StaffRoleSeeder giving the owner role exactly SeededStaffRole::OWNER_ID
     * and on that row never being deleted and recreated with a new id. If the
     * id ever moves, this index goes on being valid SQL while protecting a role
     * nobody holds - it will not fail, it will simply stop being true.
     *
     * Note what is not constrained: the role is unique per account across every
     * team, on purpose. Ordinary staff memberships are untouched, so the same
     * account can hold the staff role at as many businesses as it likes.
     */
    public function up(): void
    {
        DB::statement(
            'create unique index '.self::SINGLE_OWNER_INDEX.
            ' on model_has_roles (model_type, model_id) where role_id = '.SeededStaffRole::OWNER_ID
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::SINGLE_OWNER_INDEX);
    }
};
