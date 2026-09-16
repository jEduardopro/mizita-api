<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_CURRENCY_CODE = 'MXN';

    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('contact_email', 255)->nullable()->after('timezone');
            $table->text('about')->nullable()->after('contact_email');
            $table->string('currency_code', 3)->default(self::DEFAULT_CURRENCY_CODE)->after('about');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'about', 'currency_code']);
        });
    }
};
