<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OVERLAP_CONSTRAINT = 'appointments_staff_no_overlap';

    private const DURATION_CHECK = 'appointments_ends_after_starts';

    public function up(): void
    {
        DB::statement('create extension if not exists btree_gist');

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'starts_at']);
        });

        DB::statement(
            'alter table appointments add constraint '.self::DURATION_CHECK.
            ' check (ends_at > starts_at)'
        );

        DB::statement(
            'alter table appointments add constraint '.self::OVERLAP_CONSTRAINT.
            ' exclude using gist (staff_member_id with =, tstzrange(starts_at, ends_at) with &&)'.
            ' where (deleted_at is null)'
        );
    }

    public function down(): void
    {
        DB::statement('alter table appointments drop constraint if exists '.self::OVERLAP_CONSTRAINT);
        DB::statement('alter table appointments drop constraint if exists '.self::DURATION_CHECK);

        Schema::dropIfExists('appointments');
    }
};
