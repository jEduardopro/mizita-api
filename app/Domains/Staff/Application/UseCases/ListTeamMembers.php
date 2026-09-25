<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\ListTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListTeamMembers
{
    public function __construct(
        private readonly TeamRoster $roster,
        private readonly StaffPhoneBook $phones,
        private readonly TeamMemberPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<TeamMemberData>>
     */
    public function handle(ListTeamMembersInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $page = $this->roster->search(
                $businessId,
                $input->toQuery($this->profileIdsMatchingPhone($input->search)),
            );

            return UseCaseResponse::success($this->presenter->describePage($businessId, $page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @return list<string>
     */
    private function profileIdsMatchingPhone(?string $search): array
    {
        if ($search === null) {
            return [];
        }

        return $this->phones->profileIdsMatchingNumber($search);
    }
}
