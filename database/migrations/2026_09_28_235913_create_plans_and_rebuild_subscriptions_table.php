<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PLAN_KEY_INDEX = 'plans_key_unique_when_not_deleted';

    private const ONE_PER_BUSINESS_INDEX = 'subscriptions_business_unique_when_not_deleted';

    private const LEGACY_PERIOD_CHECK = 'subscriptions_period_ordered';

    private const LEGACY_OVERLAP_CONSTRAINT = 'subscriptions_business_no_overlap';

    private const LEGACY_EXPIRY_INDEX = 'subscriptions_active_ends_at_index';

    public function up(): void
    {
        $this->dropLegacySubscriptions();

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 32);
            $table->string('name');
            $table->bigInteger('price_amount');
            $table->char('price_currency', 3);
            $table->string('billing_interval', 16);
            $table->smallInteger('trial_days')->nullable();
            $table->string('stripe_price_id')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::PLAN_KEY_INDEX.' on plans (key) where deleted_at is null'
        );

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('payment_failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'current_period_ends_at']);
        });

        DB::statement(
            'create unique index '.self::ONE_PER_BUSINESS_INDEX.' on subscriptions (business_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');

        $this->createLegacySubscriptions();
    }

    private function dropLegacySubscriptions(): void
    {
        DB::statement('drop index if exists '.self::LEGACY_EXPIRY_INDEX);
        DB::statement('alter table if exists subscriptions drop constraint if exists '.self::LEGACY_OVERLAP_CONSTRAINT);
        DB::statement('alter table if exists subscriptions drop constraint if exists '.self::LEGACY_PERIOD_CHECK);

        Schema::dropIfExists('subscriptions');
    }

    private function createLegacySubscriptions(): void
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
            'alter table subscriptions add constraint '.self::LEGACY_PERIOD_CHECK.
            ' check (ends_at is null or ends_at >= starts_at)'
        );

        DB::statement(
            'alter table subscriptions add constraint '.self::LEGACY_OVERLAP_CONSTRAINT.
            " exclude using gist (business_id with =, tstzrange(starts_at, ends_at, '[)') with &&)".
            ' where (deleted_at is null)'
        );

        DB::statement(
            'create index '.self::LEGACY_EXPIRY_INDEX.' on subscriptions (ends_at)'.
            " where status = 'active' and deleted_at is null"
        );
    }
};
