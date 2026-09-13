<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Factories;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Seeds the row that makes an account a business user, which is how a test
 * gives a caller a tenant to operate.
 *
 * A membership is two halves - the row, and the role the account holds at that
 * business - so the factory writes both. The role assignment needs the two
 * roles to exist, which means a test using this factory has to have run
 * StaffRoleSeeder first.
 *
 * @extends Factory<StaffMemberModel>
 */
final class StaffMemberModelFactory extends Factory
{
    protected $model = StaffMemberModel::class;

    /**
     * Both foreign keys resolve to the neighbour's int primary key, which is
     * what this table references - not the uuid the entities carry.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => BusinessModel::factory(),
            'account_id' => User::factory(),
        ];
    }

    /**
     * Ordinary membership, which is what a fixture wants unless it says
     * otherwise.
     */
    public function configure(): self
    {
        return $this->afterCreating(
            fn (StaffMemberModel $member) => $this->assign($member, StaffRole::Member),
        );
    }

    /**
     * The membership signup writes: it owns the business.
     *
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

    private function assign(StaffMemberModel $member, StaffRole $role): void
    {
        $account = $member->account()->sole();

        app(StaffRoleAssignments::class)->assign($account, (int) $member->business_id, $role);
    }
}
