<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BUSINESS_UNIQUE_INDEX = 'booking_pages_business_unique';

    private const MAXIMUM_ACCENT_COLOR_LENGTH = 24;

    private const MAXIMUM_BUTTON_SHAPE_LENGTH = 16;

    private const MAXIMUM_THEME_LENGTH = 16;

    public function up(): void
    {
        Schema::create('booking_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('accent_color', self::MAXIMUM_ACCENT_COLOR_LENGTH);
            $table->string('button_shape', self::MAXIMUM_BUTTON_SHAPE_LENGTH);
            $table->string('theme', self::MAXIMUM_THEME_LENGTH);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::BUSINESS_UNIQUE_INDEX.
            ' on booking_pages (business_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::BUSINESS_UNIQUE_INDEX);

        Schema::dropIfExists('booking_pages');
    }
};
