<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NAME_UNIQUE_INDEX = 'services_business_name_lower_unique';

    private const SLUG_UNIQUE_INDEX = 'services_business_slug_unique';

    private const MAXIMUM_NAME_LENGTH = 120;

    private const MAXIMUM_SLUG_LENGTH = 60;

    private const MAXIMUM_COLOR_LENGTH = 16;

    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', self::MAXIMUM_NAME_LENGTH);
            $table->string('slug', self::MAXIMUM_SLUG_LENGTH);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->decimal('price', 10, 2);
            $table->string('color', self::MAXIMUM_COLOR_LENGTH);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'active']);
        });

        DB::statement(
            'create unique index '.self::NAME_UNIQUE_INDEX.
            ' on services (business_id, lower(name)) where deleted_at is null'
        );

        DB::statement(
            'create unique index '.self::SLUG_UNIQUE_INDEX.
            ' on services (business_id, slug) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::NAME_UNIQUE_INDEX);
        DB::statement('drop index if exists '.self::SLUG_UNIQUE_INDEX);

        Schema::dropIfExists('services');
    }
};
