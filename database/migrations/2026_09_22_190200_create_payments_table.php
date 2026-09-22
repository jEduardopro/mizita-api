<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPOINTMENT_UNIQUE_INDEX = 'payments_appointment_unique';

    private const CURRENCY_CHECK = 'payments_currency_code_valid';

    private const DISCOUNT_TYPE_CHECK = 'payments_discount_type_valid';

    private const DISCOUNT_NONE_CHECK = 'payments_discount_none_zeroed';

    private const DISCOUNT_PERCENTAGE_CHECK = 'payments_discount_percentage_bounded';

    private const DISCOUNT_WITHIN_SUBTOTAL_CHECK = 'payments_discount_within_subtotal';

    private const TOTAL_CHECK = 'payments_total_matches_subtotal';

    private const PAID_CHECK = 'payments_paid_within_total';

    private const MAXIMUM_BASIS_POINTS = 10000;

    private const MAXIMUM_DISCOUNT_TYPE_LENGTH = 16;

    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('subtotal_cents');
            $table->string('discount_type', self::MAXIMUM_DISCOUNT_TYPE_LENGTH)->default('none');
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->unsignedBigInteger('discount_amount_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'appointment_id']);
        });

        DB::statement(
            'create unique index '.self::APPOINTMENT_UNIQUE_INDEX.
            ' on payments (appointment_id) where deleted_at is null'
        );

        DB::statement(
            'alter table payments add constraint '.self::CURRENCY_CHECK.
            " check (currency_code ~ '^[A-Z]{3}$')"
        );

        DB::statement(
            'alter table payments add constraint '.self::DISCOUNT_TYPE_CHECK.
            " check (discount_type in ('none', 'percentage', 'fixed'))"
        );

        DB::statement(
            'alter table payments add constraint '.self::DISCOUNT_NONE_CHECK.
            " check (discount_type <> 'none' or (discount_value = 0 and discount_amount_cents = 0))"
        );

        DB::statement(
            'alter table payments add constraint '.self::DISCOUNT_PERCENTAGE_CHECK.
            " check (discount_type <> 'percentage' or discount_value <= ".self::MAXIMUM_BASIS_POINTS.')'
        );

        DB::statement(
            'alter table payments add constraint '.self::DISCOUNT_WITHIN_SUBTOTAL_CHECK.
            ' check (discount_amount_cents <= subtotal_cents)'
        );

        DB::statement(
            'alter table payments add constraint '.self::TOTAL_CHECK.
            ' check (total_cents = subtotal_cents - discount_amount_cents)'
        );

        DB::statement(
            'alter table payments add constraint '.self::PAID_CHECK.
            ' check (paid_cents <= total_cents)'
        );
    }

    public function down(): void
    {
        DB::statement('alter table payments drop constraint if exists '.self::PAID_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::TOTAL_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::DISCOUNT_WITHIN_SUBTOTAL_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::DISCOUNT_PERCENTAGE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::DISCOUNT_NONE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::DISCOUNT_TYPE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::CURRENCY_CHECK);
        DB::statement('drop index if exists '.self::APPOINTMENT_UNIQUE_INDEX);

        Schema::dropIfExists('payments');
    }
};
