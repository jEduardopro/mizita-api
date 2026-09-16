<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SERVICE_MORPH_ALIAS = 'service';

    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->foreignId('business_id')
                ->nullable()
                ->after('id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->index(['business_id', 'model_type']);
        });

        DB::statement(
            'update media set business_id = services.business_id from services'.
            ' where media.model_type = ? and media.model_id = services.id',
            [self::SERVICE_MORPH_ALIAS],
        );
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'model_type']);
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
