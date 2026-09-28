<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERIOD_CHECK = 'subscriptions_period_ordered';

    private const OVERLAP_CONSTRAINT = 'subscriptions_business_no_overlap';

    private const EXPIRY_INDEX = 'subscriptions_active_ends_at_index';

    public function up(): void
    {
        DB::statement('create extension if not exists btree_gist');

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->string('plan', 32);
            $table->string('status', 32);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->bigInteger('price_amount');
            $table->char('price_currency', 3);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
        });

        DB::statement(
            'alter table subscriptions add constraint '.self::PERIOD_CHECK.
            ' check (ends_at is null or ends_at >= starts_at)'
        );

        DB::statement(
            'alter table subscriptions add constraint '.self::OVERLAP_CONSTRAINT.
            " exclude using gist (business_id with =, tstzrange(starts_at, ends_at, '[)') with &&)".
            ' where (deleted_at is null)'
        );

        DB::statement(
            'create index '.self::EXPIRY_INDEX.' on subscriptions (ends_at)'.
            " where status = 'active' and deleted_at is null"
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::EXPIRY_INDEX);
        DB::statement('alter table subscriptions drop constraint if exists '.self::OVERLAP_CONSTRAINT);
        DB::statement('alter table subscriptions drop constraint if exists '.self::PERIOD_CHECK);

        Schema::dropIfExists('subscriptions');
    }
};
