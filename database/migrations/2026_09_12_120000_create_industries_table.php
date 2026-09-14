<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KEY_UNIQUE_INDEX = 'industries_key_unique';

    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['active', 'position'], 'industries_catalog_index');
        });

        DB::statement(
            'create unique index '.self::KEY_UNIQUE_INDEX.
            ' on industries ("key") where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::KEY_UNIQUE_INDEX);

        Schema::dropIfExists('industries');
    }
};
