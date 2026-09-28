<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Staff\Contracts\TeamAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;

final class SubscriptionsTeamAllowance implements TeamAllowance
{
    public function __construct(
        private readonly BusinessPlans $plans,
    ) {}

    public function includesTeam(string $businessId): bool
    {
        return $this->plans->entitlementsOf($businessId)->includesTeam;
    }

    /**
     * @param  list<string>  $businessIds
     * @return list<string>
     */
    public function businessesIncludingTeam(array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        $entitlements = $this->plans->entitlementsOfMany(array_values(array_unique($businessIds)));

        return array_values(array_filter(
            $businessIds,
            static fn (string $businessId): bool => self::includesTeamIn($entitlements[$businessId] ?? null),
        ));
    }

    private static function includesTeamIn(?PlanEntitlements $entitlements): bool
    {
        return $entitlements?->includesTeam ?? false;
    }
}
