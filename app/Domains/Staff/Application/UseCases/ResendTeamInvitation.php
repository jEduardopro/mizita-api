<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\ResendTeamInvitationInput;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class ResendTeamInvitation
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
        private readonly TeamAccountProvisioner $provisioner,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(ResendTeamInvitationInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $member = $this->members->findForBusiness(
                $this->business->currentBusinessId(),
                $input->staffMemberId,
            );

            $member->ensureInvitationPending($this->accountOf($member));

            $invitations = $member->invitationFor(
                $this->provisioner->issueTemporaryPassword($member->accountId),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($invitations as $invitation) {
            $this->events->dispatch($invitation);
        }

        return UseCaseResponse::success();
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function accountOf(StaffMember $member): AccountSnapshot
    {
        return $this->accounts->describe([$member->accountId])[0]
            ?? throw StaffMemberNotFound::forAccount($member->accountId);
    }
}
