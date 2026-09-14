<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\BusinessRoleTemplates;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A membership is two halves - the row, and the role the account holds at that
 * business - so the factory writes both. The owner role is global and seeded,
 * so a test using this factory has to have run AuthorizationSeeder first.
 *
 * @extends Factory<StaffMemberModel>
 */
final class StaffMemberModelFactory extends Factory
{
    protected $model = StaffMemberModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'account_id' => User::factory(),
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(
            fn (StaffMemberModel $member) => $this->assign($member, StaffRole::Member),
        );
    }

    /**
     * Runs after the default assignment above and replaces it, because the role
     * is synced rather than added. At most one owner assignment per account
     * survives the partial unique index, so a fixture that needs two owners
     * needs two accounts.
     */
    public function owner(): self
    {
        return $this->afterCreating(
            fn (StaffMemberModel $member) => $this->assign($member, StaffRole::Owner),
        );
    }

    /**
     * A fixture builds its business straight from BusinessModelFactory rather
     * than through onboarding, so the per-business role rows are not there.
     * Cloning the templates first is what makes the assignment find the same row
     * it finds in production; it is idempotent.
     */
    private function assign(StaffMemberModel $member, StaffRole $role): void
    {
        $account = $member->account()->sole();
        $businessKey = (int) $member->business_id;

        app(BusinessRoleTemplates::class)->cloneFor($businessKey);

        app(StaffRoleAssignments::class)->assign($account, $businessKey, $role);
    }
}
