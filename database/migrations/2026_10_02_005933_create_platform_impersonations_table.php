<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const IP_ADDRESS_MAXIMUM_LENGTH = 45;

    public function up(): void
    {
        Schema::create('platform_impersonations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('platform_admin_id')->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('ip_address', self::IP_ADDRESS_MAXIMUM_LENGTH)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['platform_admin_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_impersonations');
    }
};
