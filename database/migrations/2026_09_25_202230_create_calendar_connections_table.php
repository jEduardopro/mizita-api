<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ONE_LIVE_CONNECTION_PER_PROVIDER = 'calendar_connections_staff_provider_unique';

    private const LABEL_LENGTH = 32;

    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->index()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->index()->constrained('staff_members')->cascadeOnDelete();
            $table->string('provider', self::LABEL_LENGTH);
            $table->string('account_email');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestampTz('access_token_expires_at');
            $table->string('external_calendar_id');
            $table->string('status', self::LABEL_LENGTH);
            $table->timestampTz('connected_at');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::ONE_LIVE_CONNECTION_PER_PROVIDER.
            ' on calendar_connections (staff_member_id, provider) where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
