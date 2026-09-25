<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ONE_LIVE_LINK_PER_APPOINTMENT = 'calendar_event_links_connection_appointment_unique';

    public function up(): void
    {
        Schema::create('calendar_event_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->index()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('calendar_connection_id')->index()->constrained('calendar_connections')->cascadeOnDelete();
            $table->foreignId('appointment_id')->index()->constrained('appointments')->cascadeOnDelete();
            $table->string('external_event_id');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::ONE_LIVE_LINK_PER_APPOINTMENT.
            ' on calendar_event_links (calendar_connection_id, appointment_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_links');
    }
};
