<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessPlan;
use RuntimeException;

final class FakeBusinessPlan implements BusinessPlan
{
    /**
     * @var list<string>
     */
    public array $describedBusinessIds = [];

    /**
     * @param  array<string, array{name: 'free'|'complete', ends_at: ?string, entitlements: array{team: bool, max_active_services: ?int, booking_rules: bool, calendar_sync: bool}}>  $plansByBusiness
     */
    public function __construct(
        private readonly array $plansByBusiness = [],
    ) {}

    /**
     * @return array{name: 'free'|'complete', ends_at: ?string, entitlements: array{team: bool, max_active_services: ?int, booking_rules: bool, calendar_sync: bool}}
     */
    public function describe(string $businessId): array
    {
        $this->describedBusinessIds[] = $businessId;

        return $this->plansByBusiness[$businessId]
            ?? throw new RuntimeException("FakeBusinessPlan knows no plan for business [{$businessId}].");
    }
}
