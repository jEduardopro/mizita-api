<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FOREIGN_KEY = 'model_has_roles_business_id_foreign';

    /**
     * Ties Spatie's team column to the businesses it names.
     *
     * The package ships the column with an index and no foreign key, because it
     * cannot know what a team is. Here a team is a business, and without the
     * constraint deleting one leaves role rows pointing at nothing - rows that
     * go on granting a role at a business that no longer exists, and that would
     * be inherited by whoever next takes that id from the sequence.
     *
     * Raw SQL because the column is Spatie's, not one this schema declared:
     * naming it through the package's own configuration keeps the constraint
     * pointing wherever the team key is configured to live.
     *
     * Cascade rather than restrict: the business is the tenant, and a deleted
     * tenant takes its access with it. staff_members already cascades the same
     * way, so a membership and the role it is held under disappear together.
     *
     * A plain foreign key tolerates a null team, which is what a global,
     * teamless role assignment uses. Nothing writes one today - both seeded
     * roles are global definitions while every assignment names a business -
     * and the column is part of this table's composite primary key, so Postgres
     * rejects a null anyway. The constraint is therefore not what stands in the
     * way of a teamless assignment, and would not need changing if one ever
     * became possible.
     */
    public function up(): void
    {
        $table = StaffRoleAssignments::ASSIGNMENTS_TABLE;
        $column = StaffRoleAssignments::TEAM_COLUMN;

        DB::statement(
            'alter table '.$table.
            ' add constraint '.self::FOREIGN_KEY.
            ' foreign key ('.$column.') references businesses (id) on delete cascade'
        );
    }

    public function down(): void
    {
        DB::statement(
            'alter table '.StaffRoleAssignments::ASSIGNMENTS_TABLE.
            ' drop constraint if exists '.self::FOREIGN_KEY
        );
    }
};
