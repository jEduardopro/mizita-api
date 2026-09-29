<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Plans;

use App\Domains\Subscriptions\Application\Dtos\BusinessPlanData;
use App\Domains\Subscriptions\Application\Dtos\ShowBusinessPlanInput;
use App\Domains\Subscriptions\Application\UseCases\ShowBusinessPlan;
use App\Shared\Contracts\BusinessPlan;

final class SubscriptionsBusinessPlan implements BusinessPlan
{
    public function __construct(
        private readonly ShowBusinessPlan $showBusinessPlan,
    ) {}

    /**
     * @return array{
     *     name: 'free'|'complete',
     *     entitlements: array{team: bool, max_active_services: ?int, booking_rules: bool, calendar_sync: bool},
     * }
     */
    public function describe(string $businessId): array
    {
        /** @var BusinessPlanData $plan */
        $plan = $this->showBusinessPlan->handle(new ShowBusinessPlanInput($businessId))->value();

        return [
            'name' => $plan->plan->value,
            'entitlements' => [
                'team' => $plan->entitlements->includesTeam,
                'max_active_services' => $plan->entitlements->maxActiveServices,
                'booking_rules' => $plan->entitlements->includesBookingRules,
                'calendar_sync' => $plan->entitlements->includesCalendarSync,
            ],
        ];
    }
}
