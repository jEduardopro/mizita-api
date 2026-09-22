<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE_UNIQUE_INDEX = 'payment_methods_code_unique';

    private const MAXIMUM_CODE_LENGTH = 32;

    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', self::MAXIMUM_CODE_LENGTH);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('requires_integration')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['active', 'position']);
        });

        DB::statement(
            'create unique index '.self::CODE_UNIQUE_INDEX.
            ' on payment_methods (code) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::CODE_UNIQUE_INDEX);

        Schema::dropIfExists('payment_methods');
    }
};
