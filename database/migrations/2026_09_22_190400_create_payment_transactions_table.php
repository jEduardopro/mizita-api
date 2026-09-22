<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LIVE_INDEX = 'payment_transactions_live_index';

    private const AMOUNT_CHECK = 'payment_transactions_amount_positive';

    private const VOID_CHECK = 'payment_transactions_void_consistent';

    private const MAXIMUM_REFERENCE_LENGTH = 191;

    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('external_reference', self::MAXIMUM_REFERENCE_LENGTH)->nullable();
            $table->timestampTz('processed_at');
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by_account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payment_id', 'processed_at']);
        });

        DB::statement(
            'create index '.self::LIVE_INDEX.
            ' on payment_transactions (payment_id) where voided_at is null and deleted_at is null'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::AMOUNT_CHECK.
            ' check (amount_cents > 0)'
        );

        DB::statement(
            'alter table payment_transactions add constraint '.self::VOID_CHECK.
            ' check ((voided_at is null) = (voided_by_account_id is null))'
        );
    }

    public function down(): void
    {
        DB::statement('alter table payment_transactions drop constraint if exists '.self::VOID_CHECK);
        DB::statement('alter table payment_transactions drop constraint if exists '.self::AMOUNT_CHECK);
        DB::statement('drop index if exists '.self::LIVE_INDEX);

        Schema::dropIfExists('payment_transactions');
    }
};
