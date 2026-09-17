<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STREET_NOT_BLANK = 'addresses_street_not_blank';

    private const CITY_NOT_BLANK = 'addresses_city_not_blank';

    private const POSTAL_CODE_NOT_BLANK = 'addresses_postal_code_not_blank';

    private const MAXIMUM_CITY_LENGTH = 120;

    private const MAXIMUM_POSTAL_CODE_LENGTH = 12;

    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('city', self::MAXIMUM_CITY_LENGTH)->nullable()->change();
            $table->string('postal_code', self::MAXIMUM_POSTAL_CODE_LENGTH)->nullable()->change();
        });

        DB::statement("update addresses set city = null where btrim(city) = ''");
        DB::statement("update addresses set postal_code = null where btrim(postal_code) = ''");

        DB::statement(
            'alter table addresses add constraint '.self::STREET_NOT_BLANK.
            " check (btrim(street) <> '')"
        );
        DB::statement(
            'alter table addresses add constraint '.self::CITY_NOT_BLANK.
            " check (city is null or btrim(city) <> '')"
        );
        DB::statement(
            'alter table addresses add constraint '.self::POSTAL_CODE_NOT_BLANK.
            " check (postal_code is null or btrim(postal_code) <> '')"
        );
    }

    public function down(): void
    {
        DB::statement('alter table addresses drop constraint if exists '.self::POSTAL_CODE_NOT_BLANK);
        DB::statement('alter table addresses drop constraint if exists '.self::CITY_NOT_BLANK);
        DB::statement('alter table addresses drop constraint if exists '.self::STREET_NOT_BLANK);

        DB::statement("update addresses set city = '' where city is null");
        DB::statement("update addresses set postal_code = '' where postal_code is null");

        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('city', self::MAXIMUM_CITY_LENGTH)->nullable(false)->change();
            $table->string('postal_code', self::MAXIMUM_POSTAL_CODE_LENGTH)->nullable(false)->change();
        });
    }
};
