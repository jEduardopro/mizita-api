<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_INDEX = 'links_linkable_index';

    private const PLATFORM_UNIQUE_INDEX = 'links_owner_platform_unique';

    private const MAXIMUM_OWNER_TYPE_LENGTH = 32;

    private const MAXIMUM_PLATFORM_LENGTH = 24;

    private const MAXIMUM_URL_LENGTH = 2048;

    public function up(): void
    {
        Schema::create('links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('linkable_type', self::MAXIMUM_OWNER_TYPE_LENGTH);
            $table->bigInteger('linkable_id');
            $table->string('platform', self::MAXIMUM_PLATFORM_LENGTH);
            $table->string('url', self::MAXIMUM_URL_LENGTH);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['linkable_type', 'linkable_id'], self::OWNER_INDEX);
        });

        DB::statement(
            'create unique index '.self::PLATFORM_UNIQUE_INDEX.
            ' on links (linkable_type, linkable_id, platform) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::PLATFORM_UNIQUE_INDEX);

        Schema::dropIfExists('links');
    }
};
