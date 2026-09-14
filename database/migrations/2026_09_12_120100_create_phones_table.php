<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_UNIQUE_INDEX = 'phones_owner_unique';

    private const COUNTRY_CODE_CHECK = 'phones_country_code_check';

    private const NATIONAL_NUMBER_CHECK = 'phones_national_number_check';

    public function up(): void
    {
        Schema::create('phones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('phoneable_type', 32);
            $table->uuid('phoneable_id');
            $table->string('country_code', 2);
            $table->string('national_number', 15);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['phoneable_type', 'phoneable_id'], 'phones_phoneable_index');
        });

        DB::statement(
            'alter table phones add constraint '.self::COUNTRY_CODE_CHECK.
            " check (country_code in ('MX','US'))"
        );

        DB::statement(
            'alter table phones add constraint '.self::NATIONAL_NUMBER_CHECK.
            " check (national_number ~ '^[0-9]{7,15}$')"
        );

        DB::statement(
            'create unique index '.self::OWNER_UNIQUE_INDEX.
            ' on phones (phoneable_type, phoneable_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::OWNER_UNIQUE_INDEX);

        Schema::dropIfExists('phones');
    }
};
