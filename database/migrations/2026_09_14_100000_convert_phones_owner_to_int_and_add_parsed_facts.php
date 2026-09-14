<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns phoneable_id into the owner's int primary key and gives the parsed facts
 * their own columns.
 *
 * The rows that exist when this runs are deleted, not translated: both hold
 * Mexican numbers recorded as US and neither is valid under the new rule.
 * Emptying the table first is what makes the rest of up() trivial - nothing has
 * to be translated and the new NOT NULL columns need no backfill.
 *
 * down() is the direction that carries data, and will legitimately fail if an
 * owner row was hard deleted while the int column was in place: the uuid it
 * would have to write back no longer exists, and refusing beats inventing one.
 */
return new class extends Migration
{
    /**
     * Spelled out rather than derived, because phoneable_type carries an alias
     * registered by PhoneOwnerType and enforceMorphMap, not a table name - and a
     * migration must keep working when that mapping is rewired.
     */
    private const OWNER_TABLES = [
        'business' => 'businesses',
        'staff_member' => 'staff_members',
        'customer' => 'customers',
    ];

    private const OWNER_UNIQUE_INDEX = 'phones_owner_unique';

    private const PHONEABLE_INDEX = 'phones_phoneable_index';

    private const E164_INDEX = 'phones_e164_index';

    public function up(): void
    {
        // The rows are invalid under the new rule and repairing them would mean
        // guessing which country each one was meant to be.
        DB::table('phones')->delete();

        Schema::table('phones', function (Blueprint $table): void {
            // Dropping the column takes both of its indexes with it, silently.
            // They are recreated below under the same names; losing the partial
            // unique one would remove the one-phone-per-owner guarantee that
            // AttachPhone's upsert leans on, and nothing would fail.
            $table->dropColumn('phoneable_id');
        });

        Schema::table('phones', function (Blueprint $table): void {
            // Postgres has no cast from uuid to bigint, so the column is
            // replaced rather than altered. NOT NULL from the start, with no
            // rows left to violate it.
            $table->bigInteger('phoneable_id');
        });

        $this->createOwnerIndexes();

        Schema::table('phones', function (Blueprint $table): void {
            // Redundant with country_code today and stored anyway, because it is
            // what the E.164 form is composed from and PhoneNumber checks the
            // two agree.
            $table->smallInteger('calling_code');
            // '+' plus at most fifteen digits, which is all E.164 allows.
            $table->string('e164', 16);
            // A PhoneNumberType case, deliberately with no check constraint: the
            // value set belongs to the numbering-plan metadata, and pinning it
            // here would turn a library upgrade into a migration.
            $table->string('number_type', 24);
            // A coarse geographic label in a fixed 'en' locale, never display copy.
            $table->string('geo_description', 120)->nullable();
            // jsonb rather than json so it can be queried and indexed later.
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

    /**
     * A column whose values could not all be translated is never dropped: the
     * rows that failed name themselves here instead, and the migration stops
     * while the data it was about to lose is still there.
     */
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

    /**
     * Under the names the create-table migration gave them. Dropping
     * phoneable_id drops both implicitly, so up() and down() each put them back.
     */
    private function createOwnerIndexes(): void
    {
        Schema::table('phones', function (Blueprint $table): void {
            $table->index(['phoneable_type', 'phoneable_id'], self::PHONEABLE_INDEX);
        });

        // One phone per owner, ignoring soft deleted rows so a deleted phone
        // does not keep its owner from having a new one.
        DB::statement(
            'create unique index '.self::OWNER_UNIQUE_INDEX.
            ' on phones (phoneable_type, phoneable_id) where deleted_at is null'
        );
    }
};
