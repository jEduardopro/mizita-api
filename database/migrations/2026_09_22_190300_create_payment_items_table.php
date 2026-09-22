<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const AMOUNT_CHECK = 'payment_items_amount_not_negative';

    private const MAXIMUM_NAME_LENGTH = 120;

    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('name', self::MAXIMUM_NAME_LENGTH);
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payment_id', 'position']);
        });

        DB::statement(
            'alter table payment_items add constraint '.self::AMOUNT_CHECK.
            ' check (amount_cents >= 0)'
        );
    }

    public function down(): void
    {
        DB::statement('alter table payment_items drop constraint if exists '.self::AMOUNT_CHECK);

        Schema::dropIfExists('payment_items');
    }
};
