<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\UseCases;

use App\Domains\Availability\Application\Dtos\ReplaceScheduleInput;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Services\WeeklySchedule;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ReplaceSchedule
{
    public function __construct(
        private readonly ScheduleRuleRepository $rules,
        private readonly WeeklySchedule $schedule,
        private readonly BusinessContext $business,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<list<ScheduleRuleData>>
     */
    public function handle(ReplaceScheduleInput $input): UseCaseResponse
    {
        try {
            $businessId = $this->business->currentBusinessId();
            $rules = $this->rulesFrom($input, $businessId);

            $this->schedule->refuseOverlaps($rules);

            $this->rules->replaceForOwner($businessId, $input->ownerType, $input->ownerId, $rules);

            return UseCaseResponse::success(array_map(
                static fn (ScheduleRule $rule): ScheduleRuleData => ScheduleRuleData::fromEntity($rule),
                $rules,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @return list<ScheduleRule>
     */
    private function rulesFrom(ReplaceScheduleInput $input, string $businessId): array
    {
        $now = $this->clock->now();
        $rules = [];

        foreach ($input->intervals as $interval) {
            $rules[] = ScheduleRule::create(
                id: $this->ids->next(),
                businessId: $businessId,
                ownerType: $input->ownerType,
                ownerId: $input->ownerId,
                weekday: $interval->weekday,
                startsAt: $interval->startsAt,
                endsAt: $interval->endsAt,
                now: $now,
            );
        }

        return $rules;
    }
}
