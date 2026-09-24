<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            insert into staff_profiles (uuid, business_id, staff_member_id, created_at, updated_at)
            select gen_random_uuid(), staff_members.business_id, staff_members.id, now(), now()
            from staff_members
            where staff_members.deleted_at is null
              and not exists (
                  select 1
                  from staff_profiles
                  where staff_profiles.staff_member_id = staff_members.id
                    and staff_profiles.deleted_at is null
              )
        SQL);
    }
};
