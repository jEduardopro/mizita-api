<?php

declare(strict_types=1);

namespace App\Domains\Availability\Services;

use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\Exceptions\OverlappingScheduleIntervals;

final class WeeklySchedule
{
    /**
     * @param  list<ScheduleRule>  $rules
     *
     * @throws OverlappingScheduleIntervals
     */
    public function refuseOverlaps(array $rules): void
    {
        foreach ($this->byOwnerAndWeekday($rules) as $group) {
            $this->refuseOverlapsWithin($group);
        }
    }

    /**
     * @param  list<ScheduleRule>  $rules
     * @return array<string, list<ScheduleRule>>
     */
    private function byOwnerAndWeekday(array $rules): array
    {
        $groups = [];

        foreach ($rules as $rule) {
            $groups[self::groupKeyFor($rule)][] = $rule;
        }

        return $groups;
    }

    /**
     * @param  list<ScheduleRule>  $group
     *
     * @throws OverlappingScheduleIntervals
     */
    private function refuseOverlapsWithin(array $group): void
    {
        $sorted = self::earliestFirst($group);
        $count = count($sorted);

        for ($index = 1; $index < $count; $index++) {
            if ($sorted[$index - 1]->overlaps($sorted[$index])) {
                throw OverlappingScheduleIntervals::onWeekday($sorted[$index]->weekday->value);
            }
        }
    }

    /**
     * @param  list<ScheduleRule>  $group
     * @return list<ScheduleRule>
     */
    private static function earliestFirst(array $group): array
    {
        usort($group, static fn (ScheduleRule $one, ScheduleRule $other): int => $one->startsAt()->minutesFromMidnight()
            <=> $other->startsAt()->minutesFromMidnight());

        return array_values($group);
    }

    private static function groupKeyFor(ScheduleRule $rule): string
    {
        return $rule->ownerType->value.':'.$rule->ownerId.':'.$rule->weekday->value;
    }
}
