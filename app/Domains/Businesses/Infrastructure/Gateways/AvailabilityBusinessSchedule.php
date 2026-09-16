<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Availability\Application\Dtos\ReplaceScheduleInput;
use App\Domains\Availability\Application\UseCases\ReplaceSchedule;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

final class AvailabilityBusinessSchedule implements BusinessSchedule
{
    public function __construct(
        private readonly ScheduleRuleRepository $rules,
        private readonly ReplaceSchedule $replaceSchedule,
    ) {}

    /**
     * @return list<BusinessScheduleEntry>
     */
    public function forBusiness(string $businessId): array
    {
        return array_map(
            static fn (ScheduleRule $rule): BusinessScheduleEntry => new BusinessScheduleEntry(
                weekday: $rule->weekday->value,
                startsAt: $rule->startsAt()->toString(),
                endsAt: $rule->endsAt()->toString(),
            ),
            $this->rules->allForOwner(ScheduleOwnerType::Business, $businessId),
        );
    }

    /**
     * @param  list<BusinessScheduleEntry>  $entries
     */
    public function replaceForBusiness(string $businessId, array $entries): void
    {
        $this->replaceSchedule->handle(new ReplaceScheduleInput(
            ownerType: ScheduleOwnerType::Business,
            ownerId: $businessId,
            intervals: $this->intervalsFrom($entries),
        ))->value();
    }

    /**
     * @param  list<BusinessScheduleEntry>  $entries
     * @return list<ScheduleInterval>
     */
    private function intervalsFrom(array $entries): array
    {
        return array_map(
            static fn (BusinessScheduleEntry $entry): ScheduleInterval => new ScheduleInterval(
                weekday: Weekday::fromNumber($entry->weekday),
                startsAt: TimeOfDay::fromString($entry->startsAt),
                endsAt: TimeOfDay::fromString($entry->endsAt),
            ),
            $entries,
        );
    }
}
