<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\Dtos;

use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;

final readonly class BusinessPlanData
{
    public function __construct(
        public Plan $plan,
        public PlanEntitlements $entitlements,
    ) {}

    public static function of(Plan $plan): self
    {
        return new self(
            plan: $plan,
            entitlements: $plan->entitlements(),
        );
    }
}
