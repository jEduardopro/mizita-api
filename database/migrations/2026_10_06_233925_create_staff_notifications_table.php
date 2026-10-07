<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LABEL_LENGTH = 64;

    private const SUBJECT_INDEX = 'notification_events_subject_index';

    private const ONE_EVENT_PER_IDEMPOTENCY_KEY = 'notification_events_business_idempotency_key_unique';

    private const INBOX_INDEX = 'staff_notifications_business_recipient_read_index';

    private const ONE_DELIVERY_PER_RECIPIENT = 'staff_notifications_event_recipient_unique';

    private const ONE_UNREAD_DELIVERY_PER_COLLAPSE_KEY = 'staff_notifications_unread_collapse_key_unique';

    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', self::LABEL_LENGTH);
            $table->string('subject_type', self::LABEL_LENGTH);
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('payload');
            $table->string('idempotency_key');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id'], self::SUBJECT_INDEX);
            $table->unique(['business_id', 'idempotency_key'], self::ONE_EVENT_PER_IDEMPOTENCY_KEY);
        });

        Schema::create('staff_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->foreignId('recipient_staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->string('collapse_key');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['business_id', 'recipient_staff_member_id', 'read_at'], self::INBOX_INDEX);
            $table->unique(['notification_event_id', 'recipient_staff_member_id'], self::ONE_DELIVERY_PER_RECIPIENT);
        });

        DB::statement(
            'create unique index '.self::ONE_UNREAD_DELIVERY_PER_COLLAPSE_KEY.
            ' on staff_notifications (recipient_staff_member_id, collapse_key)'.
            ' where read_at is null and deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
        Schema::dropIfExists('notification_events');
    }
};
