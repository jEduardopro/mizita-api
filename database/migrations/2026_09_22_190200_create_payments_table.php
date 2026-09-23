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

    private const PAID_CHECK = 'payments_paid_within_total';

    private const TOTAL_NOT_NEGATIVE_CHECK = 'payments_total_not_negative';

    private const PAID_NOT_NEGATIVE_CHECK = 'payments_paid_not_negative';

    private const DISCOUNT_AMOUNT_NOT_NEGATIVE_CHECK = 'payments_discount_amount_not_negative';

    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->char('currency_code', 3);
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
            'alter table payments add constraint '.self::TOTAL_NOT_NEGATIVE_CHECK.
            ' check (total_cents >= 0)'
        );

        DB::statement(
            'alter table payments add constraint '.self::PAID_NOT_NEGATIVE_CHECK.
            ' check (paid_cents >= 0)'
        );

        DB::statement(
            'alter table payments add constraint '.self::DISCOUNT_AMOUNT_NOT_NEGATIVE_CHECK.
            ' check (discount_amount_cents >= 0)'
        );

        DB::statement(
            'alter table payments add constraint '.self::PAID_CHECK.
            ' check (paid_cents <= total_cents)'
        );
    }

    public function down(): void
    {
        DB::statement('alter table payments drop constraint if exists '.self::PAID_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::DISCOUNT_AMOUNT_NOT_NEGATIVE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::PAID_NOT_NEGATIVE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::TOTAL_NOT_NEGATIVE_CHECK);
        DB::statement('alter table payments drop constraint if exists '.self::CURRENCY_CHECK);
        DB::statement('drop index if exists '.self::APPOINTMENT_UNIQUE_INDEX);

        Schema::dropIfExists('payments');
    }
};
