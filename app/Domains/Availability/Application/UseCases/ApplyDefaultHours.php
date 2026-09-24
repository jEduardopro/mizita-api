<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\UseCases;

use App\Domains\Availability\Application\Dtos\ApplyDefaultHoursInput;
use App\Domains\Availability\Application\Dtos\ScheduleRuleData;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\ValueObjects\DefaultWeeklyHours;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class ApplyDefaultHours
{
    public function __construct(
        private readonly ScheduleRuleRepository $rules,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<list<ScheduleRuleData>>
     */
    public function handle(ApplyDefaultHoursInput $input): UseCaseResponse
    {
        try {
            $rules = $this->scheduleFor($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success(array_map(
            static fn (ScheduleRule $rule): ScheduleRuleData => ScheduleRuleData::fromEntity($rule),
            $rules,
        ));
    }

    /**
     * @return list<ScheduleRule>
     *
     * @throws InvalidTimeOfDay
     * @throws ScheduleIntervalInverted
     */
    private function scheduleFor(ApplyDefaultHoursInput $input): array
    {
        $existing = $this->rules->allForOwner($input->ownerType, $input->ownerId);

        if ($existing !== []) {
            return $existing;
        }

        $defaults = $this->defaultRulesFor($input);

        $this->rules->replaceForOwner($input->businessId, $input->ownerType, $input->ownerId, $defaults);

        return $defaults;
    }

    /**
     * @return list<ScheduleRule>
     *
     * @throws InvalidTimeOfDay
     * @throws ScheduleIntervalInverted
     */
    private function defaultRulesFor(ApplyDefaultHoursInput $input): array
    {
        $now = $this->clock->now();
        $rules = [];

        foreach (DefaultWeeklyHours::intervals() as $interval) {
            $rules[] = ScheduleRule::create(
                id: $this->ids->next(),
                businessId: $input->businessId,
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
