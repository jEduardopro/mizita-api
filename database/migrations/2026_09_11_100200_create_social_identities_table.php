<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROVIDER_USER_UNIQUE_INDEX = 'social_identities_provider_user_unique';

    public function up(): void
    {
        Schema::create('social_identities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_user_id');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::PROVIDER_USER_UNIQUE_INDEX.
            ' on social_identities (provider, provider_user_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::PROVIDER_USER_UNIQUE_INDEX);

        Schema::dropIfExists('social_identities');
    }
};
