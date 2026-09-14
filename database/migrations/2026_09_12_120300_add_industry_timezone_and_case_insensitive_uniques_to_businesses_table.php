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

    private const FALLBACK_INDUSTRY_KEY = 'other';

    private const FALLBACK_TIMEZONE = 'America/Mexico_City';

    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->foreignId('industry_id')->nullable()->after('slug')
                ->index()
                ->constrained('industries')
                ->restrictOnDelete();

            $table->string('timezone', 64)->nullable()->after('industry_id');

            $table->dropUnique(['slug']);
        });

        $this->backfill();

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable(false)->change();
            $table->unsignedBigInteger('industry_id')->nullable(false)->change();
        });

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
