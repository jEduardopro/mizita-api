<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessPlan
{
    /**
     * @return array{
     *     name: 'free'|'complete',
     *     entitlements: array{team: bool, max_active_services: ?int, booking_rules: bool, calendar_sync: bool},
     * }
     */
    public function describe(string $businessId): array;
}
