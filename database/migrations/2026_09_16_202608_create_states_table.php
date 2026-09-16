<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE_UNIQUE_INDEX = 'states_country_code_lower_unique';

    private const LISTING_INDEX = 'states_country_active_position_index';

    private const MAXIMUM_COUNTRY_CODE_LENGTH = 2;

    private const MAXIMUM_CODE_LENGTH = 6;

    private const MAXIMUM_NAME_LENGTH = 120;

    public function up(): void
    {
        Schema::create('states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('country_code', self::MAXIMUM_COUNTRY_CODE_LENGTH);
            $table->string('code', self::MAXIMUM_CODE_LENGTH);
            $table->string('name', self::MAXIMUM_NAME_LENGTH);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['country_code', 'active', 'position'], self::LISTING_INDEX);
        });

        DB::statement(
            'create unique index '.self::CODE_UNIQUE_INDEX.
            ' on states (country_code, lower(code)) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::CODE_UNIQUE_INDEX);

        Schema::dropIfExists('states');
    }
};
