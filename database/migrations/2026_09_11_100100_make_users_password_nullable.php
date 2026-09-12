<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account that only ever signs in with Google has no password at all.
     * Null says exactly that; an empty string would be a hash that some future
     * comparison could be tricked into accepting.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reversible only while every row still has a password: rolling back with
     * Google-only accounts present is a data problem, not a schema one.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
        });
    }
};
