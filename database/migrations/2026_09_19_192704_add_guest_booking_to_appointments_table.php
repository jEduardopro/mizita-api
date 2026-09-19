<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OVERLAP_CONSTRAINT = 'appointments_staff_no_overlap';

    private const REFERENCE_CODE_UNIQUE_INDEX = 'appointments_reference_code_unique';

    private const MANAGE_TOKEN_UNIQUE_INDEX = 'appointments_manage_token_hash_unique';

    private const REFERENCE_CODE_LENGTH = 8;

    private const MANAGE_TOKEN_HASH_LENGTH = 64;

    private const LIFECYCLE_LABEL_LENGTH = 16;

    private const DEFAULT_SOURCE = 'admin';

    private const HEXADECIMAL_ALPHABET = '0123456789abcdef';

    private const REFERENCE_CODE_ALPHABET = '23456789ABCDEFGH';

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('reference_code', self::REFERENCE_CODE_LENGTH)->nullable()->after('notes');
            $table->string('manage_token_hash', self::MANAGE_TOKEN_HASH_LENGTH)->nullable()->after('reference_code');
            $table->timestampTz('manage_token_expires_at')->nullable()->after('manage_token_hash');
            $table->timestampTz('cancelled_at')->nullable()->after('manage_token_expires_at');
            $table->string('cancelled_by', self::LIFECYCLE_LABEL_LENGTH)->nullable()->after('cancelled_at');
            $table->string('source', self::LIFECYCLE_LABEL_LENGTH)->default(self::DEFAULT_SOURCE)->after('cancelled_by');
        });

        DB::statement(
            'update appointments set reference_code = '.self::randomCodeExpression().
            ' where reference_code is null'
        );

        DB::statement('alter table appointments alter column reference_code set not null');

        DB::statement(
            'create unique index '.self::REFERENCE_CODE_UNIQUE_INDEX.
            ' on appointments (reference_code) where deleted_at is null'
        );

        DB::statement(
            'create unique index '.self::MANAGE_TOKEN_UNIQUE_INDEX.
            ' on appointments (manage_token_hash) where deleted_at is null'
        );

        DB::statement('alter table appointments drop constraint if exists '.self::OVERLAP_CONSTRAINT);

        DB::statement(
            'alter table appointments add constraint '.self::OVERLAP_CONSTRAINT.
            ' exclude using gist (staff_member_id with =, tstzrange(starts_at, ends_at) with &&)'.
            ' where (deleted_at is null and cancelled_at is null)'
        );
    }

    public function down(): void
    {
        DB::statement('alter table appointments drop constraint if exists '.self::OVERLAP_CONSTRAINT);
        DB::statement('drop index if exists '.self::MANAGE_TOKEN_UNIQUE_INDEX);
        DB::statement('drop index if exists '.self::REFERENCE_CODE_UNIQUE_INDEX);

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn([
                'reference_code',
                'manage_token_hash',
                'manage_token_expires_at',
                'cancelled_at',
                'cancelled_by',
                'source',
            ]);
        });

        DB::statement(
            'alter table appointments add constraint '.self::OVERLAP_CONSTRAINT.
            ' exclude using gist (staff_member_id with =, tstzrange(starts_at, ends_at) with &&)'.
            ' where (deleted_at is null)'
        );
    }

    private static function randomCodeExpression(): string
    {
        $randomHexadecimal = 'substr(md5(random()::text || id::text), 1, '.self::REFERENCE_CODE_LENGTH.')';

        return "translate({$randomHexadecimal}, '".self::HEXADECIMAL_ALPHABET."', '".self::REFERENCE_CODE_ALPHABET."')";
    }
};
