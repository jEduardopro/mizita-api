<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;

final class AvailabilityPublishedSchedule implements PublishedSchedule
{
    public function __construct(
        private readonly ScheduleRuleRepository $rules,
    ) {}

    /**
     * @return list<PublicScheduleEntry>
     */
    public function forBusiness(string $businessId): array
    {
        return array_map(
            static fn (ScheduleRule $rule): PublicScheduleEntry => new PublicScheduleEntry(
                weekday: $rule->weekday->value,
                startsAt: $rule->startsAt()->toString(),
                endsAt: $rule->endsAt()->toString(),
            ),
            $this->rules->allForOwner(ScheduleOwnerType::Business, $businessId),
        );
    }
}
