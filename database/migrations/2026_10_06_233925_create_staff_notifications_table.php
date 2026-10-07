<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_LENGTH = 64;

    private const INBOX_INDEX = 'staff_notifications_business_recipient_read_index';

    private const ONE_NOTIFICATION_PER_EVENT = 'staff_notifications_type_appointment_recipient_unique';

    public function up(): void
    {
        Schema::create('staff_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('recipient_staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->string('type', self::TYPE_LENGTH);
            $table->foreignId('appointment_id')->nullable()->index()->constrained('appointments')->cascadeOnDelete();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['business_id', 'recipient_staff_member_id', 'read_at'], self::INBOX_INDEX);
            $table->unique(['type', 'appointment_id', 'recipient_staff_member_id'], self::ONE_NOTIFICATION_PER_EVENT);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
    }
};
