<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\ShowTeamMemberInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowTeamMember
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly TeamMemberPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<TeamMemberData>
     */
    public function handle(ShowTeamMemberInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $member = $this->members->findForBusiness(
                $this->business->currentBusinessId(),
                $input->staffMemberId,
            );

            return UseCaseResponse::success($this->presenter->describe($member));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
