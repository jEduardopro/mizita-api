<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Exceptions\AccountHasUpcomingAppointments;
use App\Domains\Staff\Application\Dtos\CheckAccountMembershipsRemovalInput;
use App\Domains\Staff\Application\Dtos\RemoveAccountMembershipsInput;
use App\Domains\Staff\Application\UseCases\CheckAccountMembershipsRemoval;
use App\Domains\Staff\Application\UseCases\RemoveAccountMemberships;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\TeamMemberHasUpcomingAppointments;

final class StaffTeamMemberships implements TeamMemberships
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly CheckAccountMembershipsRemoval $checkMembershipsRemoval,
        private readonly RemoveAccountMemberships $removeMemberships,
    ) {}

    public function ownedBusinessIdOf(string $accountId): ?string
    {
        return $this->members->ownedBusinessIdOf($accountId);
    }

    public function hasUpcomingAppointmentsOutsideOwnedBusiness(string $accountId): bool
    {
        $removal = $this->checkMembershipsRemoval
            ->handle(new CheckAccountMembershipsRemovalInput($accountId))
            ->value();

        return ! $removal->removable();
    }

    /**
     * @throws AccountHasUpcomingAppointments
     */
    public function leaveTeamsNotOwned(string $accountId): void
    {
        try {
            $this->removeMemberships->handle(new RemoveAccountMembershipsInput($accountId))->value();
        } catch (TeamMemberHasUpcomingAppointments) {
            throw AccountHasUpcomingAppointments::forAccount($accountId);
        }
    }
}
