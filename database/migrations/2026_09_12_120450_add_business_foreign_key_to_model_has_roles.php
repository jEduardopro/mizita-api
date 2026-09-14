<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FOREIGN_KEY = 'model_has_roles_business_id_foreign';

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
