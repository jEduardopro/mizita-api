<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An account holds exactly one role at a business, or none. Two things already
 * lean on that: StaffRoleAssignments::roleFor() takes the first row it finds,
 * and EloquentBusinessMembership::businessIdsFor() would return a business twice
 * for an account holding two roles there.
 *
 * It forecloses multi-role memberships on purpose, and does not constrain how
 * many businesses an account may hold a role at - the owner index added earlier
 * is the one limiting ownership, and it is a different shape.
 */
return new class extends Migration
{
    private const SINGLE_ROLE_INDEX = 'model_has_roles_single_role_per_business';

    public function up(): void
    {
        $this->refuseAccountsHoldingTwoRoles();

        DB::statement(
            'create unique index '.self::SINGLE_ROLE_INDEX.
            ' on '.StaffRoleAssignments::ASSIGNMENTS_TABLE.
            ' (model_type, model_id, '.StaffRoleAssignments::TEAM_COLUMN.')'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::SINGLE_ROLE_INDEX);
    }

    private function refuseAccountsHoldingTwoRoles(): void
    {
        $table = StaffRoleAssignments::ASSIGNMENTS_TABLE;
        $team = StaffRoleAssignments::TEAM_COLUMN;

        /** @var array<int, object{model_type: string, model_id: int, business: ?int, total: int}> $duplicates */
        $duplicates = DB::select(
            "select model_type, model_id, {$team} as business, count(*) as total from {$table}".
            " group by model_type, model_id, {$team} having count(*) > 1"
        );

        if ($duplicates === []) {
            return;
        }

        $rows = implode(', ', array_map(
            static fn (object $row): string => "{$row->model_type}:{$row->model_id} at business {$row->business} x{$row->total}",
            $duplicates,
        ));

        throw new RuntimeException(
            'Cannot add '.self::SINGLE_ROLE_INDEX.
            ": these already hold more than one role at one business [{$rows}]. ".
            'Decide which role each of them keeps and run the migration again.'
        );
    }
};
