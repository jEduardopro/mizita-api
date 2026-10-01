<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SALES_REPORT_INDEX = 'payments_business_created_at_live_index';

    public function up(): void
    {
        DB::statement(
            'create index '.self::SALES_REPORT_INDEX.
            ' on payments (business_id, created_at) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::SALES_REPORT_INDEX);
    }
};
