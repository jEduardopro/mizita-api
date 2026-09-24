<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\UseCases;

use App\Domains\Availability\Application\Dtos\MyScheduleData;
use App\Domains\Availability\Application\Dtos\ReplaceMyScheduleInput;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffMembership;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class ReplaceMySchedule
{
    public function __construct(
        private readonly StaffMembership $memberships,
        private readonly ScheduleRuleRepository $rules,
        private readonly WeeklySchedule $schedule,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<MyScheduleData>
     */
    public function handle(ReplaceMyScheduleInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $staffId = $this->memberships->staffMemberIdOf($businessId, $input->accountId);
            $rules = $this->rulesFrom($input, $businessId, $staffId);

            $this->schedule->refuseOverlaps($rules);

            $this->transactions->run(
                fn () => $this->rules->replaceForOwner($businessId, ScheduleOwnerType::StaffMember, $staffId, $rules),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        if ($rules !== []) {
            return UseCaseResponse::success(MyScheduleData::own($rules));
        }

        return UseCaseResponse::success(MyScheduleData::inheritedFrom(
            $this->rules->allForOwner(ScheduleOwnerType::Business, $businessId),
        ));
    }

    /**
     * @return list<ScheduleRule>
     *
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     * @throws ScheduleIntervalInverted
     */
    private function rulesFrom(ReplaceMyScheduleInput $input, string $businessId, string $staffId): array
    {
        $now = $this->clock->now();
        $rules = [];

        foreach ($input->intervals() as $interval) {
            $rules[] = ScheduleRule::create(
                id: $this->ids->next(),
                businessId: $businessId,
                ownerType: ScheduleOwnerType::StaffMember,
                ownerId: $staffId,
                weekday: $interval->weekday,
                startsAt: $interval->startsAt,
                endsAt: $interval->endsAt,
                now: $now,
            );
        }

        return $rules;
    }
}
