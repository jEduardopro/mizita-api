<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BY_PAYMENT_INDEX = 'payment_transactions_by_payment_index';

    private const ONE_BREAKDOWN_INDEX = 'payment_transactions_one_breakdown';

    private const TOTAL_CHECK = 'payment_transactions_total_positive';

    private const TYPE_CHECK = 'payment_transactions_type_valid';

    private const DISCOUNT_TYPE_CHECK = 'payment_transactions_discount_type_valid';

    private const DISCOUNT_NONE_CHECK = 'payment_transactions_discount_none_zeroed';

    private const DISCOUNT_PERCENTAGE_CHECK = 'payment_transactions_discount_percentage_bounded';

    private const DISCOUNT_WITHIN_SUBTOTAL_CHECK = 'payment_transactions_discount_within_subtotal';

    private const SUBTOTAL_CONSISTENT_CHECK = 'payment_transactions_subtotal_consistent';

    private const BREAKDOWN_NOT_NEGATIVE_CHECK = 'payment_transactions_breakdown_not_negative';

    private const VOID_ACTOR_CHECK = 'payment_transactions_void_has_actor';

    private const VOID_BREAKDOWN_CHECK = 'payment_transactions_void_breakdown_zeroed';

    private const MAXIMUM_BASIS_POINTS = 10000;

    private const MAXIMUM_TYPE_LENGTH = 16;

    private const MAXIMUM_DISCOUNT_TYPE_LENGTH = 16;

    private const MAXIMUM_REFERENCE_LENGTH = 191;

    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', self::MAXIMUM_TYPE_LENGTH);
            $table->unsignedBigInteger('subtotal_pre_discount_cents')->default(0);
            $table->string('discount_type', self::MAXIMUM_DISCOUNT_TYPE_LENGTH)->default('none');
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->unsignedBigInteger('subtotal_discount_cents')->default(0);
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->string('external_reference', self::MAXIMUM_REFERENCE_LENGTH)->nullable();
            $table->timestampTz('processed_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payment_id', 'processed_at']);
        });

        DB::statement(
            'create index '.self::BY_PAYMENT_INDEX.
            ' on payment_transactions (payment_id, type) where deleted_at is null'
        );

        DB::statement(
            'create unique index '.self::ONE_BREAKDOWN_INDEX.
            ' on payment_transactions (payment_id)'.
            ' where subtotal_pre_discount_cents > 0 and deleted_at is null'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::TOTAL_CHECK.
            ' check (total_cents > 0)'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::TYPE_CHECK.
            " check (type in ('approved', 'void', 'refund', 'failed'))"
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::DISCOUNT_TYPE_CHECK.
            " check (discount_type in ('none', 'percentage', 'fixed'))"
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::DISCOUNT_NONE_CHECK.
            " check (discount_type <> 'none' or (discount_value = 0 and subtotal_discount_cents = 0))"
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::DISCOUNT_PERCENTAGE_CHECK.
            " check (discount_type <> 'percentage' or discount_value <= ".self::MAXIMUM_BASIS_POINTS.')'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::DISCOUNT_WITHIN_SUBTOTAL_CHECK.
            ' check (subtotal_discount_cents <= subtotal_pre_discount_cents)'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::SUBTOTAL_CONSISTENT_CHECK.
            ' check (subtotal_cents = subtotal_pre_discount_cents - subtotal_discount_cents)'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::BREAKDOWN_NOT_NEGATIVE_CHECK.
            ' check (subtotal_pre_discount_cents >= 0 and discount_value >= 0'.
            ' and subtotal_discount_cents >= 0 and subtotal_cents >= 0 and total_cents >= 0)'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::VOID_ACTOR_CHECK.
            " check (type <> 'void' or account_id is not null)"
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::VOID_BREAKDOWN_CHECK.
            " check (type <> 'void' or (subtotal_pre_discount_cents = 0 and subtotal_discount_cents = 0".
            " and subtotal_cents = 0 and discount_value = 0 and discount_type = 'none'))"
        );
    }

    public function down(): void
    {
        DB::statement('alter table payment_transactions drop constraint if exists '.self::VOID_BREAKDOWN_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::VOID_ACTOR_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::BREAKDOWN_NOT_NEGATIVE_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::SUBTOTAL_CONSISTENT_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::DISCOUNT_WITHIN_SUBTOTAL_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::DISCOUNT_PERCENTAGE_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::DISCOUNT_NONE_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::DISCOUNT_TYPE_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::TYPE_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::TOTAL_CHECK);
        DB::statement('drop index if exists '.self::ONE_BREAKDOWN_INDEX);
        DB::statement('drop index if exists '.self::BY_PAYMENT_INDEX);

        Schema::dropIfExists('payment_transactions');
    }
};
