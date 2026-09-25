<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\CheckAccountMembershipsRemovalInput;
use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\RemovalBlocker;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use DateTimeImmutable;

final class CheckAccountMembershipsRemoval
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly UpcomingAppointments $upcomingAppointments,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<TeamMemberRemovalData>
     */
    public function handle(CheckAccountMembershipsRemovalInput $input): UseCaseResponse
    {
        return UseCaseResponse::success(new TeamMemberRemovalData($this->blockerFor($input->accountId)));
    }

    private function blockerFor(string $accountId): ?RemovalBlocker
    {
        $now = $this->clock->now();

        foreach ($this->members->allInOpenBusinessesForAccount($accountId) as $membership) {
            if ($this->hasUpcomingAppointments($membership, $now)) {
                return RemovalBlocker::UpcomingAppointments;
            }
        }

        return null;
    }

    private function hasUpcomingAppointments(StaffMember $membership, DateTimeImmutable $now): bool
    {
        if ($membership->ownsBusiness()) {
            return false;
        }

        return $this->upcomingAppointments->existFor($membership->businessId, $membership->id, $now);
    }
}
