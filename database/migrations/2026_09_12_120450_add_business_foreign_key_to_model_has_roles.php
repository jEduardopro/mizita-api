<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FOREIGN_KEY = 'model_has_roles_business_id_foreign';

    /**
     * The package ships the team column with an index and no foreign key,
     * because it cannot know what a team is. Without the constraint, deleting a
     * business leaves role rows granting access at a business that no longer
     * exists - rows inherited by whoever next takes that id from the sequence.
     *
     * Raw SQL because the column is Spatie's: naming it through the package's
     * own configuration keeps the constraint pointing wherever the team key is
     * configured to live.
     *
     * Cascade rather than restrict: a deleted tenant takes its access with it,
     * the same way staff_members already cascades.
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
