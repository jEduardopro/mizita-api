<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RevealedTemporaryPassword;
use App\Domains\Staff\Application\Dtos\RevealTeamMemberTemporaryPasswordInput;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\TeamTemporaryPasswords;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TemporaryPasswordUnavailable;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RevealTeamMemberTemporaryPassword
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
        private readonly TeamTemporaryPasswords $temporaryPasswords,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<RevealedTemporaryPassword>
     */
    public function handle(RevealTeamMemberTemporaryPasswordInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $member = $this->members->findForBusiness(
                $this->business->currentBusinessId(),
                $input->staffMemberId,
            );

            $member->ensureTemporaryPasswordRevealable($this->accountOf($member));

            return UseCaseResponse::success(new RevealedTemporaryPassword($this->temporaryPasswordOf($member)));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function accountOf(StaffMember $member): AccountSnapshot
    {
        return $this->accounts->describe([$member->accountId])[0]
            ?? throw StaffMemberNotFound::forAccount($member->accountId);
    }

    /**
     * @throws TemporaryPasswordUnavailable
     */
    private function temporaryPasswordOf(StaffMember $member): string
    {
        return $this->temporaryPasswords->revealFor($member->accountId)
            ?? throw TemporaryPasswordUnavailable::for($member->id);
    }
}
