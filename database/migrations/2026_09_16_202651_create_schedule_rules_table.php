<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BUSINESS_OWNER_INDEX = 'schedule_rules_business_owner_index';

    private const OWNER_WEEKDAY_INDEX = 'schedule_rules_owner_weekday_index';

    private const WEEKDAY_CHECK = 'schedule_rules_weekday_check';

    private const INTERVAL_CHECK = 'schedule_rules_interval_check';

    private const MAXIMUM_OWNER_TYPE_LENGTH = 32;

    private const FIRST_WEEKDAY = 1;

    private const LAST_WEEKDAY = 7;

    public function up(): void
    {
        Schema::create('schedule_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('owner_type', self::MAXIMUM_OWNER_TYPE_LENGTH);
            $table->bigInteger('owner_id');
            $table->unsignedSmallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'owner_type', 'owner_id'], self::BUSINESS_OWNER_INDEX);
            $table->index(['owner_type', 'owner_id', 'weekday'], self::OWNER_WEEKDAY_INDEX);
        });

        DB::statement(
            'alter table schedule_rules add constraint '.self::WEEKDAY_CHECK.
            ' check (weekday between '.self::FIRST_WEEKDAY.' and '.self::LAST_WEEKDAY.')'
        );

        DB::statement(
            'alter table schedule_rules add constraint '.self::INTERVAL_CHECK.
            ' check (ends_at > starts_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_rules');
    }
};
