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
            // A morph alias, never an FQCN, which would not fit in 32 chars. The
            // aliases are the cases of PhoneOwnerType, registered by each owning
            // domain with Relation::enforceMorphMap.
            $table->string('phoneable_type', 32);
            // A deliberate exception to the int foreign key rule: a morph column
            // carries no foreign key, so an int would buy no referential
            // integrity and only force Phones to import three other domains'
            // models to translate uuid -> int. (Later migrated to an int anyway.)
            $table->uuid('phoneable_id');
            $table->string('country_code', 2);
            $table->string('national_number', 15);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['phoneable_type', 'phoneable_id'], 'phones_phoneable_index');
        });

        // The value objects already reject anything else, but seeders, console
        // commands and manual fixes reach the table without passing through them.
        DB::statement(
            'alter table phones add constraint '.self::COUNTRY_CODE_CHECK.
            " check (country_code in ('MX','US'))"
        );

        DB::statement(
            'alter table phones add constraint '.self::NATIONAL_NUMBER_CHECK.
            " check (national_number ~ '^[0-9]{7,15}$')"
        );

        // Enforced here rather than by a read-then-write, which two concurrent
        // requests would lose. Partial, so a soft deleted phone does not keep its
        // owner from having a new one.
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
