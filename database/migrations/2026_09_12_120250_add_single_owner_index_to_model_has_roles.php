<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SINGLE_OWNER_INDEX = 'model_has_roles_single_owner_unique';

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
