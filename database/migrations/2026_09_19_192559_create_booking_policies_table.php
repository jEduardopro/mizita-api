<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BUSINESS_UNIQUE_INDEX = 'booking_policies_business_unique';

    private const LEAD_TIME_CHECK = 'booking_policies_lead_time_check';

    private const BOOKING_WINDOW_CHECK = 'booking_policies_booking_window_check';

    private const SLOT_GRANULARITY_CHECK = 'booking_policies_slot_granularity_check';

    private const CANCELLATION_WINDOW_CHECK = 'booking_policies_cancellation_window_check';

    private const MAXIMUM_LEAD_TIME_MINUTES = 43200;

    private const MINIMUM_BOOKING_WINDOW_MINUTES = 1;

    private const MAXIMUM_BOOKING_WINDOW_MINUTES = 525600;

    private const MINIMUM_SLOT_GRANULARITY_MINUTES = 5;

    private const MAXIMUM_SLOT_GRANULARITY_MINUTES = 60;

    private const SLOT_GRANULARITY_STEP_MINUTES = 5;

    private const MAXIMUM_CANCELLATION_WINDOW_MINUTES = 43200;

    private const DEFAULT_LEAD_TIME_MINUTES = 0;

    private const DEFAULT_SLOT_GRANULARITY_MINUTES = 15;

    private const DEFAULT_CANCELLATION_WINDOW_MINUTES = 120;

    public function up(): void
    {
        Schema::create('booking_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedInteger('lead_time_minutes')->default(self::DEFAULT_LEAD_TIME_MINUTES);
            $table->unsignedInteger('booking_window_minutes')->nullable();
            $table->unsignedSmallInteger('slot_granularity_minutes')->default(self::DEFAULT_SLOT_GRANULARITY_MINUTES);
            $table->unsignedInteger('cancellation_window_minutes')->nullable()->default(self::DEFAULT_CANCELLATION_WINDOW_MINUTES);
            $table->text('policy_message')->nullable();
            $table->boolean('display_on_booking_page')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::BUSINESS_UNIQUE_INDEX.
            ' on booking_policies (business_id) where deleted_at is null'
        );

        DB::statement(
            'alter table booking_policies add constraint '.self::LEAD_TIME_CHECK.
            ' check (lead_time_minutes <= '.self::MAXIMUM_LEAD_TIME_MINUTES.')'
        );

        DB::statement(
            'alter table booking_policies add constraint '.self::BOOKING_WINDOW_CHECK.
            ' check (booking_window_minutes is null or booking_window_minutes between '.
            self::MINIMUM_BOOKING_WINDOW_MINUTES.' and '.self::MAXIMUM_BOOKING_WINDOW_MINUTES.')'
        );

        DB::statement(
            'alter table booking_policies add constraint '.self::SLOT_GRANULARITY_CHECK.
            ' check (slot_granularity_minutes between '.
            self::MINIMUM_SLOT_GRANULARITY_MINUTES.' and '.self::MAXIMUM_SLOT_GRANULARITY_MINUTES.
            ' and slot_granularity_minutes % '.self::SLOT_GRANULARITY_STEP_MINUTES.' = 0)'
        );

        DB::statement(
            'alter table booking_policies add constraint '.self::CANCELLATION_WINDOW_CHECK.
            ' check (cancellation_window_minutes is null or cancellation_window_minutes <= '.
            self::MAXIMUM_CANCELLATION_WINDOW_MINUTES.')'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::BUSINESS_UNIQUE_INDEX);

        Schema::dropIfExists('booking_policies');
    }
};
