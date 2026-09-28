<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BOOKING_SLUG_INDEX = 'staff_profiles_business_booking_slug_unique';

    private const MAXIMUM_BOOKING_SLUG_LENGTH = 60;

    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('booking_slug', self::MAXIMUM_BOOKING_SLUG_LENGTH)->nullable()->after('about');
        });

        DB::statement(
            'create unique index '.self::BOOKING_SLUG_INDEX.
            ' on staff_profiles (business_id, booking_slug) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::BOOKING_SLUG_INDEX);

        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropColumn('booking_slug');
        });
    }
};
