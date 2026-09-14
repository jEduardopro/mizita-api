<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_TABLES = [
        'business' => 'businesses',
        'staff_member' => 'staff_members',
    ];

    private const OWNER_UNIQUE_INDEX = 'phones_owner_unique';

    private const PHONEABLE_INDEX = 'phones_phoneable_index';

    private const E164_INDEX = 'phones_e164_index';

    public function up(): void
    {
        DB::table('phones')->delete();

        Schema::table('phones', function (Blueprint $table): void {
            $table->dropColumn('phoneable_id');
        });

        Schema::table('phones', function (Blueprint $table): void {
            $table->bigInteger('phoneable_id');
        });

        $this->createOwnerIndexes();

        Schema::table('phones', function (Blueprint $table): void {
            $table->smallInteger('calling_code');
            $table->string('e164', 16);
            $table->string('number_type', 24);
            $table->string('geo_description', 120)->nullable();
            $table->jsonb('timezones')->default('[]');
            $table->index('e164', self::E164_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('phones', function (Blueprint $table): void {
            $table->dropIndex(self::E164_INDEX);
            $table->dropColumn(['calling_code', 'e164', 'number_type', 'geo_description', 'timezones']);
        });

        Schema::table('phones', function (Blueprint $table): void {
            $table->uuid('phoneable_uuid')->nullable();
        });

        foreach (self::OWNER_TABLES as $alias => $table) {
            DB::statement(
                "update phones p set phoneable_uuid = o.uuid from {$table} o".
                ' where p.phoneable_type = ? and p.phoneable_id = o.id',
                [$alias]
            );
        }

        $this->refuseUntranslatedRows('phoneable_uuid', 'phoneable_id');

        Schema::table('phones', function (Blueprint $table): void {
            $table->dropColumn('phoneable_id');
        });

        Schema::table('phones', function (Blueprint $table): void {
            $table->renameColumn('phoneable_uuid', 'phoneable_id');
        });

        DB::statement('alter table phones alter column phoneable_id set not null');

        $this->createOwnerIndexes();
    }

    private function refuseUntranslatedRows(string $translated, string $source): void
    {
        /** @var array<int, object{uuid: string, phoneable_type: string}> $stranded */
        $stranded = DB::table('phones')
            ->whereNull($translated)
            ->get(['uuid', 'phoneable_type']);

        if ($stranded->isEmpty()) {
            return;
        }

        $rows = $stranded
            ->map(static fn (object $row): string => "{$row->phoneable_type}:{$row->uuid}")
            ->implode(', ');

        throw new RuntimeException(
            "Cannot convert phones.{$source}: no owner row matches [{$rows}]. ".
            'Delete or repair those phones and run the migration again.'
        );
    }

    private function createOwnerIndexes(): void
    {
        Schema::table('phones', function (Blueprint $table): void {
            $table->index(['phoneable_type', 'phoneable_id'], self::PHONEABLE_INDEX);
        });

        DB::statement(
            'create unique index '.self::OWNER_UNIQUE_INDEX.
            ' on phones (phoneable_type, phoneable_id) where deleted_at is null'
        );
    }
};
