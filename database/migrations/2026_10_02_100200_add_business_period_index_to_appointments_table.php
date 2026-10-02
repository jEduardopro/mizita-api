<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const AGENDA_INDEX = 'appointments_business_period_index';

    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(
            'create index concurrently if not exists '.self::AGENDA_INDEX.
            ' on appointments using gist (business_id, tstzrange(starts_at, ends_at))'.
            ' where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index concurrently if exists '.self::AGENDA_INDEX);
    }
};
