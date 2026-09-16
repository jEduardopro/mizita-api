<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Entities\ScheduleRule;

final readonly class ScheduleRuleData
{
    public function __construct(
        public string $id,
        public int $weekday,
        public string $startsAt,
        public string $endsAt,
    ) {}

    public static function fromEntity(ScheduleRule $rule): self
    {
        return new self(
            id: $rule->id,
            weekday: $rule->weekday->value,
            startsAt: $rule->startsAt()->toString(),
            endsAt: $rule->endsAt()->toString(),
        );
    }
}
