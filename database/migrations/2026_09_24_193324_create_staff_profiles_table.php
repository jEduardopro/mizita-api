<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STAFF_MEMBER_INDEX = 'staff_profiles_staff_member_unique';

    private const MAXIMUM_JOB_TITLE_LENGTH = 120;

    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->index()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained('staff_members')->cascadeOnDelete();
            $table->string('job_title', self::MAXIMUM_JOB_TITLE_LENGTH)->nullable();
            $table->text('about')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'create unique index '.self::STAFF_MEMBER_INDEX.
            ' on staff_profiles (staff_member_id) where deleted_at is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::STAFF_MEMBER_INDEX);

        Schema::dropIfExists('staff_profiles');
    }
};
