<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\Contracts\BusinessPlans;
use App\Domains\Platform\ValueObjects\Plan;
use DateTimeImmutable;

final class FakeBusinessPlans implements BusinessPlans
{
    /**
     * @var array<string, Plan>
     */
    private array $plans = [];

    /**
     * @var list<list<string>>
     */
    public array $businessIdsAsked = [];

    /**
     * @var list<DateTimeImmutable>
     */
    public array $instantsAsked = [];

    public function granting(string $businessId, Plan $plan): self
    {
        $this->plans[$businessId] = $plan;

        return $this;
    }

    /**
     * @param  list<string>  $businessIds
     * @return array<string, Plan>
     */
    public function plansOf(array $businessIds, DateTimeImmutable $now): array
    {
        $this->businessIdsAsked[] = $businessIds;
        $this->instantsAsked[] = $now;

        $plans = [];

        foreach ($businessIds as $businessId) {
            $plans[$businessId] = $this->plans[$businessId] ?? Plan::Free;
        }

        return $plans;
    }
}
