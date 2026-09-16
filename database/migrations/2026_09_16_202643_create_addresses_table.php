<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_INDEX = 'addresses_addressable_index';

    private const OWNER_UNIQUE_INDEX = 'addresses_owner_unique';

    private const MAXIMUM_OWNER_TYPE_LENGTH = 32;

    private const MAXIMUM_STREET_LENGTH = 160;

    private const MAXIMUM_CITY_LENGTH = 120;

    private const MAXIMUM_POSTAL_CODE_LENGTH = 12;

    private const MAXIMUM_COUNTRY_CODE_LENGTH = 2;

    private const DEFAULT_COUNTRY_CODE = 'MX';

    private const COORDINATE_PRECISION = 10;

    private const COORDINATE_SCALE = 7;

    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('addressable_type', self::MAXIMUM_OWNER_TYPE_LENGTH);
            $table->bigInteger('addressable_id');
            $table->string('street', self::MAXIMUM_STREET_LENGTH);
            $table->string('city', self::MAXIMUM_CITY_LENGTH);
            $table->foreignId('state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->string('postal_code', self::MAXIMUM_POSTAL_CODE_LENGTH);
            $table->string('country_code', self::MAXIMUM_COUNTRY_CODE_LENGTH)
                ->default(self::DEFAULT_COUNTRY_CODE);
            $table->decimal('latitude', self::COORDINATE_PRECISION, self::COORDINATE_SCALE)->nullable();
            $table->decimal('longitude', self::COORDINATE_PRECISION, self::COORDINATE_SCALE)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['addressable_type', 'addressable_id'], self::OWNER_INDEX);
        });

        DB::statement(
            'create unique index '.self::OWNER_UNIQUE_INDEX.
            ' on addresses (addressable_type, addressable_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::OWNER_UNIQUE_INDEX);

        Schema::dropIfExists('addresses');
    }
};
