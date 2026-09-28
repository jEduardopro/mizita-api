<?php

declare(strict_types=1);

use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::update(<<<'SQL'
            update services
            set active = false, updated_at = ?
            where id in (
                select id
                from (
                    select id, row_number() over (partition by business_id order by created_at, id) as position
                    from services
                    where active = true
                      and deleted_at is null
                ) ranked
                where ranked.position > ?
            )
        SQL, [now(), PlanEntitlements::FREE_ACTIVE_SERVICE_LIMIT]);
    }
};
