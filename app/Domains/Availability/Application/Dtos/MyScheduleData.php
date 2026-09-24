<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Entities\ScheduleRule;

final readonly class MyScheduleData
{
    /**
     * @param  list<ScheduleRuleData>  $schedule
     */
    private function __construct(
        public bool $inherited,
        public array $schedule,
    ) {}

    /**
     * @param  list<ScheduleRule>  $rules
     */
    public static function own(array $rules): self
    {
        return new self(inherited: false, schedule: self::describe($rules));
    }

    /**
     * @param  list<ScheduleRule>  $businessRules
     */
    public static function inheritedFrom(array $businessRules): self
    {
        return new self(inherited: true, schedule: self::describe($businessRules));
    }

    /**
     * @param  list<ScheduleRule>  $rules
     * @return list<ScheduleRuleData>
     */
    private static function describe(array $rules): array
    {
        return array_map(ScheduleRuleData::fromEntity(...), $rules);
    }
}
