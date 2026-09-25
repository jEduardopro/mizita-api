<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberPhotoInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RemoveTeamMemberPhoto
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly StaffProfilePhotos $photos,
        private readonly TeamMemberPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<TeamMemberData>
     */
    public function handle(RemoveTeamMemberPhotoInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForBusiness($businessId, $input->staffMemberId);
            $profile = $this->profiles->findForStaffMember($businessId, $member->id);

            $this->photos->remove($businessId, $profile->id);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($this->presenter->describe($member));
    }
}
