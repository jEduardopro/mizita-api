<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUG_UNIQUE_INDEX = 'businesses_slug_lower_unique';

    private const NAME_UNIQUE_INDEX = 'businesses_name_lower_unique';

    /** The catalog row every business predating the industry column is filed under. */
    private const FALLBACK_INDUSTRY_KEY = 'other';

    /** Where the product launched. Every pre-existing row was created there. */
    private const FALLBACK_TIMEZONE = 'America/Mexico_City';

    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // Nullable only until the backfill below fills it in.
            $table->foreignId('industry_id')->nullable()->after('slug')
                ->index()
                ->constrained('industries')
                // restrictOnDelete is load-bearing. A cascade on a catalog
                // foreign key means deleting one catalog row deletes every
                // business filed under it - a whole industry of tenants gone
                // because somebody tidied up a lookup table.
                ->restrictOnDelete();

            // The business's IANA identifier, the only source of its local
            // time. 64 characters covers every identifier PHP ships.
            $table->string('timezone', 64)->nullable()->after('industry_id');

            // Replaced below by a partial, case insensitive index.
            $table->dropUnique(['slug']);
        });

        $this->backfill();

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable(false)->change();
            $table->unsignedBigInteger('industry_id')->nullable(false)->change();
        });

        // Partial and case insensitive, which fixes two things the plain unique
        // index on slug got wrong:
        //
        // - It matched byte for byte, so "Barberia" and "barberia" were two
        //   different businesses to the database and one address to a customer.
        // - It covered soft deleted rows, so a deleted business kept its slug
        //   reserved forever. The auto suffix then quietly handed the next
        //   signup "-2" for a name nobody was using.
        //
        // The same treatment on name is what makes the uniqueness the use case
        // reads for actually enforceable.
        DB::statement(
            'create unique index '.self::SLUG_UNIQUE_INDEX.
            ' on businesses (lower(slug)) where deleted_at is null'
        );

        DB::statement(
            'create unique index '.self::NAME_UNIQUE_INDEX.
            ' on businesses (lower(name)) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::SLUG_UNIQUE_INDEX);
        DB::statement('drop index if exists '.self::NAME_UNIQUE_INDEX);

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('industry_id');
            $table->dropColumn('timezone');
            $table->unique('slug');
        });
    }

    /**
     * Gives the rows that predate these columns a value, so both can be made
     * mandatory.
     *
     * Soft deleted rows are included: NOT NULL does not care that a row is
     * hidden from the application.
     *
     * On a fresh database there is nothing to fill in, which is the usual case
     * and the reason the industry lookup is not done unconditionally - it would
     * fail on a table that has not been seeded yet.
     */
    private function backfill(): void
    {
        $businesses = DB::table('businesses')->whereNull('industry_id');

        if (! $businesses->exists()) {
            return;
        }

        $fallbackIndustryId = DB::table('industries')
            ->where('key', self::FALLBACK_INDUSTRY_KEY)
            ->value('id');

        if ($fallbackIndustryId === null) {
            throw new RuntimeException(
                'Cannot backfill businesses.industry_id: the "'.self::FALLBACK_INDUSTRY_KEY.
                '" industry is missing. Seed the industries catalog first.'
            );
        }

        $businesses->update([
            'industry_id' => $fallbackIndustryId,
            'timezone' => self::FALLBACK_TIMEZONE,
        ]);
    }
};
