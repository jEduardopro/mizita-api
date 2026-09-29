<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

enum Plan: string
{
    case Free = 'free';

    case Complete = 'complete';

    public function entitlements(): PlanEntitlements
    {
        return match ($this) {
            self::Free => PlanEntitlements::free(),
            self::Complete => PlanEntitlements::complete(),
        };
    }
}
