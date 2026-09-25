<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RemoveAccountMembershipsInput;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\TeamMemberHasUpcomingAppointments;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\TransactionManager;

final class RemoveAccountMemberships
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly UpcomingAppointments $upcomingAppointments,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(RemoveAccountMembershipsInput $input): UseCaseResponse
    {
        try {
            $this->ensureNoUpcomingAppointments(
                self::withoutOwnership($this->members->allInOpenBusinessesForAccount($input->accountId)),
            );

            $memberships = self::withoutOwnership($this->members->allForAccount($input->accountId));

            $this->transactions->run(function () use ($memberships): void {
                foreach ($memberships as $membership) {
                    $this->members->delete($membership->businessId, $membership->id);
                }
            });

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @param  list<StaffMember>  $memberships
     * @return list<StaffMember>
     */
    private static function withoutOwnership(array $memberships): array
    {
        return array_values(array_filter(
            $memberships,
            static fn (StaffMember $membership): bool => ! $membership->ownsBusiness(),
        ));
    }

    /**
     * @param  list<StaffMember>  $memberships
     *
     * @throws TeamMemberHasUpcomingAppointments
     */
    private function ensureNoUpcomingAppointments(array $memberships): void
    {
        $now = $this->clock->now();

        foreach ($memberships as $membership) {
            if ($this->upcomingAppointments->existFor($membership->businessId, $membership->id, $now)) {
                throw TeamMemberHasUpcomingAppointments::for($membership->id);
            }
        }
    }
}
