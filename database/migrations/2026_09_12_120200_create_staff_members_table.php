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
            $table->foreignId('business_id')->index()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('account_id')->index()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

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
