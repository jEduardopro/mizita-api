<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const CUSTOMER_HISTORY_INDEX = ['business_id', 'customer_id', 'starts_at'];

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(self::CUSTOMER_HISTORY_INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(self::CUSTOMER_HISTORY_INDEX);
        });
    }
};
