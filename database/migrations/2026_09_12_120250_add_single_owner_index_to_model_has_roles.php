<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SINGLE_OWNER_INDEX = 'model_has_roles_single_owner_unique';

    /**
     * An account owns one business or none.
     *
     * Read this before changing how roles are seeded. An index predicate cannot
     * look a role up by name, so this one names the owner role's primary key
     * literally: the guarantee depends on AuthorizationSeeder giving that role
     * exactly SeededStaffRole::OWNER_ID and on the row never being recreated
     * with a new id. If the id moves, this index stays valid SQL while
     * protecting a role nobody holds - it will not fail, it will stop being true.
     *
     * Unique per account across every team, on purpose: ordinary staff
     * memberships are untouched.
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
