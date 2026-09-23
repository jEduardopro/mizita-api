<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATE_NAME_NOT_BLANK = 'addresses_state_name_not_blank';

    private const MAXIMUM_STATE_NAME_LENGTH = 120;

    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('state_name', self::MAXIMUM_STATE_NAME_LENGTH)->nullable();
        });

        DB::statement(
            'alter table addresses add constraint '.self::STATE_NAME_NOT_BLANK.
            " check (state_name is null or btrim(state_name) <> '')"
        );
    }

    public function down(): void
    {
        DB::statement('alter table addresses drop constraint if exists '.self::STATE_NAME_NOT_BLANK);

        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn('state_name');
        });
    }
};
