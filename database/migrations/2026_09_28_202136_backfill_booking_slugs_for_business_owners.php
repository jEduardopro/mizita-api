<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Domains\Staff\Services\SlugAllocator;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $allocator = new SlugAllocator;

        foreach ($this->ownerProfilesWithoutSlug() as $owner) {
            $base = BookingSlug::fromName((string) $owner->name);

            DB::table('staff_profiles')
                ->where('id', $owner->profile_id)
                ->update([
                    'booking_slug' => $allocator->allocate($base, $this->slugsTakenIn((int) $owner->business_id))->value,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @return list<object{profile_id: int, business_id: int, name: string}>
     */
    private function ownerProfilesWithoutSlug(): array
    {
        return DB::select(<<<'SQL'
            select staff_profiles.id as profile_id, staff_profiles.business_id, users.name
            from staff_profiles
            join staff_members on staff_members.id = staff_profiles.staff_member_id
            join users on users.id = staff_members.account_id
            join model_has_roles on model_has_roles.model_id = users.id
                and model_has_roles.model_type = ?
                and model_has_roles.business_id = staff_members.business_id
                and model_has_roles.role_id = ?
            where staff_profiles.booking_slug is null
              and staff_profiles.deleted_at is null
              and staff_members.deleted_at is null
            order by staff_profiles.id
        SQL, [(new User)->getMorphClass(), SeededStaffRole::OWNER_ID]);
    }

    /**
     * @return list<string>
     */
    private function slugsTakenIn(int $businessKey): array
    {
        return DB::table('staff_profiles')
            ->where('business_id', $businessKey)
            ->whereNull('deleted_at')
            ->whereNotNull('booking_slug')
            ->pluck('booking_slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->values()
            ->all();
    }
};
