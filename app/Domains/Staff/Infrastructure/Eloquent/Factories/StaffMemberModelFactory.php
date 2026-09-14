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

    public function owner(): self
    {
        return $this->afterCreating(
            fn (StaffMemberModel $member) => $this->assign($member, StaffRole::Owner),
        );
    }

    private function assign(StaffMemberModel $member, StaffRole $role): void
    {
        $account = $member->account()->sole();
        $businessKey = (int) $member->business_id;

        app(BusinessRoleTemplates::class)->cloneFor($businessKey);

        app(StaffRoleAssignments::class)->assign($account, $businessKey, $role);
    }
}
