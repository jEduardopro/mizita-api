<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\CheckTeamMemberRemovalInput;
use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\RemovalBlocker;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class CheckTeamMemberRemoval
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly UpcomingAppointments $upcomingAppointments,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<TeamMemberRemovalData>
     */
    public function handle(CheckTeamMemberRemovalInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $member = $this->members->findForBusiness(
                $this->business->currentBusinessId(),
                $input->staffMemberId,
            );

            return UseCaseResponse::success(new TeamMemberRemovalData($this->blockerFor($member)));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function blockerFor(StaffMember $member): ?RemovalBlocker
    {
        return $member->removalBlocker() ?? $this->upcomingAppointmentsBlocker($member);
    }

    private function upcomingAppointmentsBlocker(StaffMember $member): ?RemovalBlocker
    {
        if (! $this->upcomingAppointments->existFor($member->businessId, $member->id, $this->clock->now())) {
            return null;
        }

        return RemovalBlocker::UpcomingAppointments;
    }
}
