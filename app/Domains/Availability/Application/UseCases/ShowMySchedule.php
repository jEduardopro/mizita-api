<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\UseCases;

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ShowMyScheduleInput;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffMembership;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowMySchedule
{
    public function __construct(
        private readonly StaffMembership $memberships,
        private readonly ScheduleRuleRepository $rules,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<MyScheduleData>
     */
    public function handle(ShowMyScheduleInput $input): UseCaseResponse
    {
        try {
            $businessId = $this->business->currentBusinessId();
            $staffId = $this->memberships->staffMemberIdOf($businessId, $input->accountId);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $ownRules = $this->rules->allForOwner(ScheduleOwnerType::StaffMember, $staffId);

        if ($ownRules !== []) {
            return UseCaseResponse::success(MyScheduleData::own($ownRules));
        }

        return UseCaseResponse::success(MyScheduleData::inheritedFrom(
            $this->rules->allForOwner(ScheduleOwnerType::Business, $businessId),
        ));
    }
}
