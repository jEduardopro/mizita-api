<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\UseCases;

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ShowStaffMemberScheduleInput;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffRoster;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowStaffMemberSchedule
{
    public function __construct(
        private readonly StaffRoster $roster,
        private readonly ScheduleRuleRepository $rules,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<MyScheduleData>
     */
    public function handle(ShowStaffMemberScheduleInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $this->roster->confirmMembership($businessId, $input->staffMemberId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $ownRules = $this->rules->allForOwner(ScheduleOwnerType::StaffMember, $input->staffMemberId);

        if ($ownRules !== []) {
            return UseCaseResponse::success(MyScheduleData::own($ownRules));
        }

        return UseCaseResponse::success(MyScheduleData::inheritedFrom(
            $this->rules->allForOwner(ScheduleOwnerType::Business, $businessId),
        ));
    }
}
