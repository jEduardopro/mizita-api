<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const EMAIL_UNIQUE_INDEX = 'customers_business_email_lower_unique';

    private const MAXIMUM_NAME_LENGTH = 120;

    private const MAXIMUM_EMAIL_LENGTH = 254;

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', self::MAXIMUM_NAME_LENGTH);
            $table->string('email', self::MAXIMUM_EMAIL_LENGTH)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'name']);
        });

        DB::statement(
            'create unique index '.self::EMAIL_UNIQUE_INDEX.
            ' on customers (business_id, lower(email)) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::EMAIL_UNIQUE_INDEX);

        Schema::dropIfExists('customers');
    }
};
