<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUBJECT_COLUMN = 'subject_staff_member_id';

    private const SUBJECT_INDEX = 'staff_notifications_subject_staff_member_id_index';

    private const ONE_UNREAD_SCHEDULE_CHANGE_PER_SUBJECT = 'staff_notifications_unread_schedule_change_unique';

    private const STAFF_SCHEDULE_CHANGED_TYPE = 'staff_schedule_changed';

    public function up(): void
    {
        Schema::table('staff_notifications', function (Blueprint $table): void {
            $table->foreignId(self::SUBJECT_COLUMN)
                ->nullable()
                ->constrained('staff_members')
                ->cascadeOnDelete();

            $table->index(self::SUBJECT_COLUMN, self::SUBJECT_INDEX);
        });

        DB::statement(
            'create unique index '.self::ONE_UNREAD_SCHEDULE_CHANGE_PER_SUBJECT.
            ' on staff_notifications (recipient_staff_member_id, subject_staff_member_id)'.
            " where type = '".self::STAFF_SCHEDULE_CHANGED_TYPE."' and read_at is null and deleted_at is null"
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::ONE_UNREAD_SCHEDULE_CHANGE_PER_SUBJECT);

        Schema::table('staff_notifications', function (Blueprint $table): void {
            $table->dropIndex(self::SUBJECT_INDEX);
            $table->dropConstrainedForeignId(self::SUBJECT_COLUMN);
        });
    }
};
