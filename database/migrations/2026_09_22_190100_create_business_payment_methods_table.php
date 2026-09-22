<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METHOD_UNIQUE_INDEX = 'business_payment_methods_method_unique';

    public function up(): void
    {
        Schema::create('business_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'enabled', 'position']);
        });

        DB::statement(
            'create unique index '.self::METHOD_UNIQUE_INDEX.
            ' on business_payment_methods (business_id, payment_method_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::METHOD_UNIQUE_INDEX);

        Schema::dropIfExists('business_payment_methods');
    }
};
