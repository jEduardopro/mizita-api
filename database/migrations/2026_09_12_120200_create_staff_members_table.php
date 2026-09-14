<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BUSINESS_ACCOUNT_INDEX = 'staff_members_business_account_unique';

    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Indexed explicitly: Postgres indexes neither side of a foreign key
            // for you, and each of these is at once a cascade target and a hot
            // lookup path - account_id resolves the tenant on every request.
            $table->foreignId('business_id')->index()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('account_id')->index()->constrained('users')->cascadeOnDelete();
            // No role column: what a membership may do is a Spatie role attached
            // to the user and scoped to the business through Spatie's team key.
            // The one-owner-per-account guarantee moved there too, to the partial
            // unique index on model_has_roles added in a later migration.
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial on deleted_at, so a membership that was given up does not bar
        // the same person from being taken on again.
        DB::statement(
            'create unique index '.self::BUSINESS_ACCOUNT_INDEX.
            ' on staff_members (business_id, account_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::BUSINESS_ACCOUNT_INDEX);

        Schema::dropIfExists('staff_members');
    }
};
