<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUG_UNIQUE_INDEX = 'businesses_slug_lower_unique';

    private const NAME_UNIQUE_INDEX = 'businesses_name_lower_unique';

    private const DUE_FOR_PURGE_INDEX = 'businesses_due_for_purge_index';

    private const RESERVED_PREDICATE = 'deleted_at is null or closed_at is not null';

    private const LIVE_PREDICATE = 'deleted_at is null';

    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('purged_at')->nullable();
            $table->foreignId('closed_by_account_id')->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });

        DB::statement(
            'create index '.self::DUE_FOR_PURGE_INDEX.
            ' on businesses (closed_at) where closed_at is not null and purged_at is null'
        );

        $this->rebuildUniqueIndexes(self::RESERVED_PREDICATE);
    }

    public function down(): void
    {
        $this->rebuildUniqueIndexes(self::LIVE_PREDICATE);

        DB::statement('drop index if exists '.self::DUE_FOR_PURGE_INDEX);

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by_account_id');
            $table->dropColumn(['closed_at', 'purged_at']);
        });
    }

    private function rebuildUniqueIndexes(string $predicate): void
    {
        DB::statement('drop index if exists '.self::SLUG_UNIQUE_INDEX);
        DB::statement('drop index if exists '.self::NAME_UNIQUE_INDEX);

        DB::statement(
            'create unique index '.self::SLUG_UNIQUE_INDEX.
            ' on businesses (lower(slug)) where '.$predicate
        );

        DB::statement(
            'create unique index '.self::NAME_UNIQUE_INDEX.
            ' on businesses (lower(name)) where '.$predicate
        );
    }
};
