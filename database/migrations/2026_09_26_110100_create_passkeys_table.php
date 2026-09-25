<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CREDENTIAL_UNIQUE_INDEX = 'passkeys_credential_id_unique';

    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id');
            $table->json('credential');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
        });

        DB::statement(
            'create unique index '.self::CREDENTIAL_UNIQUE_INDEX.
            ' on passkeys (credential_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::CREDENTIAL_UNIQUE_INDEX);

        Schema::dropIfExists('passkeys');
    }
};
