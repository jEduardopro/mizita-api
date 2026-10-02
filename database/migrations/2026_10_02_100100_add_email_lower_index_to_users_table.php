<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EMAIL_LOOKUP_INDEX = 'users_email_lower_index';

    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(
            'create index concurrently if not exists '.self::EMAIL_LOOKUP_INDEX.
            ' on users (lower(email))'
        );
    }

    public function down(): void
    {
        DB::statement('drop index concurrently if exists '.self::EMAIL_LOOKUP_INDEX);
    }
};
