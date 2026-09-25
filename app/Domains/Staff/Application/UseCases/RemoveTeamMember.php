<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberInput;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\TeamMemberHasUpcomingAppointments;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class RemoveTeamMember
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly UpcomingAppointments $upcomingAppointments,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(RemoveTeamMemberInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForBusiness($businessId, $input->staffMemberId);

            $member->ensureRemovable();
            $this->ensureNoUpcomingAppointments($member);

            $this->transactions->run(function () use ($businessId, $member): void {
                $this->members->delete($businessId, $member->id);
            });

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws TeamMemberHasUpcomingAppointments
     */
    private function ensureNoUpcomingAppointments(StaffMember $member): void
    {
        if ($this->upcomingAppointments->existFor($member->businessId, $member->id, $this->clock->now())) {
            throw TeamMemberHasUpcomingAppointments::for($member->id);
        }
    }
}
