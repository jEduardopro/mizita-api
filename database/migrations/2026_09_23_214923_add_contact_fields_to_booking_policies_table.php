<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HIDDEN = 'hidden';

    private const OPTIONAL = 'optional';

    private const REQUIRED = 'required';

    private const REQUIREMENT_LENGTH = 16;

    private const DEFAULT_REQUIREMENTS = [
        'phone_field' => self::REQUIRED,
        'email_field' => self::OPTIONAL,
        'address_field' => self::HIDDEN,
    ];

    public function up(): void
    {
        Schema::table('booking_policies', function (Blueprint $table): void {
            foreach (self::DEFAULT_REQUIREMENTS as $column => $default) {
                $table->string($column, self::REQUIREMENT_LENGTH)->default($default);
            }
        });

        foreach (array_keys(self::DEFAULT_REQUIREMENTS) as $column) {
            DB::statement(
                'alter table booking_policies add constraint '.self::checkNameFor($column).
                ' check ('.$column." in ('".self::HIDDEN."', '".self::OPTIONAL."', '".self::REQUIRED."'))"
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::DEFAULT_REQUIREMENTS) as $column) {
            DB::statement('alter table booking_policies drop constraint if exists '.self::checkNameFor($column));
        }

        Schema::table('booking_policies', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::DEFAULT_REQUIREMENTS));
        });
    }

    private static function checkNameFor(string $column): string
    {
        return 'booking_policies_'.$column.'_check';
    }
};
